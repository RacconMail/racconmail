use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct EmailDocument {
    pub id: String,
    pub user_id: String,
    pub folder: String,
    pub subject: String,
    pub sender: String,
    pub sender_name: Option<String>,
    pub recipients: Vec<String>,
    pub snippet: String,
    pub body_text: String,
    pub date: String,
    pub timestamp: i64,
    pub is_unread: bool,
    pub is_starred: bool,
    pub has_attachments: bool,
    pub tags: Vec<String>,
}

#[derive(Debug, Deserialize)]
pub struct SearchQuery {
    pub user_id: String,
    pub query: String,
    pub folder: Option<String>,
    pub limit: Option<usize>,
    pub offset: Option<usize>,
    pub starred_only: Option<bool>,
    pub unread_only: Option<bool>,
}

#[derive(Debug, Serialize)]
pub struct SearchHit {
    pub document: EmailDocument,
    pub score: f32,
    pub highlights: Vec<String>,
}

#[derive(Debug, Serialize)]
pub struct SearchResponse {
    pub query: String,
    pub total_hits: usize,
    pub took_micros: u128,
    pub hits: Vec<SearchHit>,
}

#[derive(Debug, Serialize)]
pub struct EngineStats {
    pub total_documents: usize,
    pub total_users: usize,
    pub unique_terms: usize,
    pub uptime_seconds: u64,
    pub memory_allocated_approx_kb: usize,
}

#[derive(Debug, Deserialize)]
pub struct BatchIndexRequest {
    pub documents: Vec<EmailDocument>,
}

#[derive(Debug, Serialize)]
pub struct BatchIndexResponse {
    pub indexed_count: usize,
    pub took_micros: u128,
}
