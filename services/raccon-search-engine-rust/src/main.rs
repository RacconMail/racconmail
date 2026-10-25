mod models;
mod indexer;

use std::sync::Arc;
use axum::{
    extract::{Query, State},
    http::StatusCode,
    response::IntoResponse,
    routing::{get, post},
    Json, Router,
};
use tower_http::cors::{Any, CorsLayer};
use tower_http::trace::TraceLayer;
use tracing_subscriber::{layer::SubscriberExt, util::SubscriberInitExt};

use models::{BatchIndexRequest, BatchIndexResponse, EngineStats, SearchQuery, SearchResponse};
use indexer::SearchEngineState;

#[tokio::main]
async fn main() {
    tracing_subscriber::registry()
        .with(tracing_subscriber::EnvFilter::try_from_default_env().unwrap_or_else(|_| "info".into()))
        .with(tracing_subscriber::fmt::layer())
        .init();

    let state = Arc::new(SearchEngineState::new());

    // Allow cross-origin requests from the webmail client
    let cors = CorsLayer::new()
        .allow_origin(Any)
        .allow_methods(Any)
        .allow_headers(Any);

    let app = Router::new()
        .route("/health", get(health_handler))
        .route("/api/v1/search", get(search_handler))
        .route("/api/v1/index/batch", post(batch_index_handler))
        .route("/api/v1/stats", get(stats_handler))
        .layer(cors)
        .layer(TraceLayer::new_for_http())
        .with_state(state);

    let bind_addr = std::env::var("BIND_ADDR").unwrap_or_else(|_| "0.0.0.0:8088".to_string());
    tracing::info!("🦝 Raccon Search & Recommendation Engine (Rust) listening on {}", bind_addr);

    let listener = tokio::net::TcpListener::bind(&bind_addr).await.unwrap();
    axum::serve(listener, app).await.unwrap();
}

async fn health_handler() -> impl IntoResponse {
    (StatusCode::OK, Json(serde_json::json!({
        "status": "healthy",
        "service": "raccon-search-engine-rust",
        "engine": "BM25+CandidateRanking"
    })))
}

async fn search_handler(
    State(state): State<Arc<SearchEngineState>>,
    Query(query): Query<SearchQuery>,
) -> impl IntoResponse {
    let index = state.index.read().unwrap();
    let response: SearchResponse = index.search(&query);
    (StatusCode::OK, Json(response))
}

async fn batch_index_handler(
    State(state): State<Arc<SearchEngineState>>,
    Json(payload): Json<BatchIndexRequest>,
) -> impl IntoResponse {
    let start = std::time::Instant::now();
    let mut index = state.index.write().unwrap();
    let count = payload.documents.len();

    for doc in payload.documents {
        index.index_document(doc);
    }

    let response = BatchIndexResponse {
        indexed_count: count,
        took_micros: start.elapsed().as_micros(),
    };
    (StatusCode::OK, Json(response))
}

async fn stats_handler(State(state): State<Arc<SearchEngineState>>) -> impl IntoResponse {
    let index = state.index.read().unwrap();
    let (docs, users, terms) = index.stats();
    let stats = EngineStats {
        total_documents: docs,
        total_users: users,
        unique_terms: terms,
        uptime_seconds: state.start_time.elapsed().as_secs(),
        memory_allocated_approx_kb: (docs * 2 + terms * 1),
    };
    (StatusCode::OK, Json(stats))
}
