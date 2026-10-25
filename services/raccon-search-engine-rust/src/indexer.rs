use std::collections::{HashMap, HashSet};
use std::sync::RwLock;
use crate::models::{EmailDocument, SearchHit, SearchQuery, SearchResponse};

const BM25_K1: f32 = 1.2;
const BM25_B: f32 = 0.75;

pub struct InvertedIndex {
    // Term -> DocId -> Term Frequency
    postings: HashMap<String, HashMap<String, u32>>,
    // DocId -> Document
    documents: HashMap<String, EmailDocument>,
    // User -> Set of DocIds for fast tenant isolation
    user_docs: HashMap<String, HashSet<String>>,
    // Document lengths (for BM25 normalization)
    doc_lengths: HashMap<String, usize>,
    // Average document length
    total_length: usize,
}

impl InvertedIndex {
    pub fn new() -> Self {
        Self {
            postings: HashMap::new(),
            documents: HashMap::new(),
            user_docs: HashMap::new(),
            doc_lengths: HashMap::new(),
            total_length: 0,
        }
    }

    fn tokenize(text: &str) -> Vec<String> {
        text.to_lowercase()
            .split(|c: char| !c.is_alphanumeric() && c != '@' && c != '.')
            .filter(|s| s.len() > 1)
            .map(|s| s.to_string())
            .collect()
    }

    pub fn index_document(&mut self, doc: EmailDocument) {
        let doc_id = doc.id.clone();
        let user_id = doc.user_id.clone();

        // Index full content (subject with high weight, sender, body)
        let subject_tokens = Self::tokenize(&doc.subject);
        let sender_tokens = Self::tokenize(&doc.sender);
        let body_tokens = Self::tokenize(&doc.body_text);

        let mut term_freq: HashMap<String, u32> = HashMap::new();
        // Give subject terms 3x weight
        for t in subject_tokens {
            *term_freq.entry(t).or_insert(0) += 3;
        }
        // Give sender terms 2x weight
        for t in sender_tokens {
            *term_freq.entry(t).or_insert(0) += 2;
        }
        // Body terms standard weight
        for t in body_tokens {
            *term_freq.entry(t).or_insert(0) += 1;
        }

        let doc_len = term_freq.values().sum::<u32>() as usize;
        self.doc_lengths.insert(doc_id.clone(), doc_len);
        self.total_length += doc_len;

        for (term, freq) in term_freq {
            self.postings
                .entry(term)
                .or_insert_with(HashMap::new)
                .insert(doc_id.clone(), freq);
        }

        self.user_docs
            .entry(user_id)
            .or_insert_with(HashSet::new)
            .insert(doc_id.clone());

        self.documents.insert(doc_id, doc);
    }

    pub fn search(&self, query: &SearchQuery) -> SearchResponse {
        let start = std::time::Instant::now();
        let query_tokens = Self::tokenize(&query.query);

        if query_tokens.is_empty() {
            return SearchResponse {
                query: query.query.clone(),
                total_hits: 0,
                took_micros: start.elapsed().as_micros(),
                hits: Vec::new(),
            };
        }

        let user_allowed_docs = match self.user_docs.get(&query.user_id) {
            Some(docs) => docs,
            None => return SearchResponse {
                query: query.query.clone(),
                total_hits: 0,
                took_micros: start.elapsed().as_micros(),
                hits: Vec::new(),
            },
        };

        let num_docs = self.documents.len() as f32;
        let avg_dl = if !self.doc_lengths.is_empty() {
            self.total_length as f32 / self.doc_lengths.len() as f32
        } else {
            1.0
        };

        // Score map: doc_id -> score
        let mut scores: HashMap<String, f32> = HashMap::new();

        for term in &query_tokens {
            if let Some(postings_map) = self.postings.get(term) {
                let doc_freq = postings_map.len() as f32;
                // IDF calculation (standard BM25)
                let idf = ((num_docs - doc_freq + 0.5) / (doc_freq + 0.5) + 1.0).ln();

                for (doc_id, &tf) in postings_map {
                    if !user_allowed_docs.contains(doc_id) {
                        continue;
                    }

                    let dl = *self.doc_lengths.get(doc_id).unwrap_or(&1) as f32;
                    let tf_norm = (tf as f32 * (BM25_K1 + 1.0))
                        / (tf as f32 + BM25_K1 * (1.0 - BM25_B + BM25_B * (dl / avg_dl)));

                    let score = idf * tf_norm;
                    *scores.entry(doc_id.clone()).or_insert(0.0) += score;
                }
            }
        }

        // Apply filters & candidate scoring boosts
        let mut filtered_hits: Vec<SearchHit> = scores
            .into_iter()
            .filter_map(|(doc_id, score)| {
                let doc = self.documents.get(&doc_id)?;

                // Folder filter
                if let Some(ref f) = query.folder {
                    if !f.eq_ignore_ascii_case("all") && !doc.folder.eq_ignore_ascii_case(f) {
                        return None;
                    }
                }

                // Starred only filter
                if query.starred_only.unwrap_or(false) && !doc.is_starred {
                    return None;
                }

                // Unread only filter
                if query.unread_only.unwrap_or(false) && !doc.is_unread {
                    return None;
                }

                // Candidate Boosts (Modeled after X's recommendation weights)
                let mut final_score = score;
                if doc.is_starred {
                    final_score *= 1.25; // 25% boost for starred items
                }
                if doc.is_unread {
                    final_score *= 1.15; // 15% boost for unread items
                }
                // Time decay / recency boost
                let age_hours = ((chrono::Utc::now().timestamp() - doc.timestamp) / 3600).max(1) as f32;
                let recency_multiplier = (1.0 / (1.0 + (age_hours / 168.0))).max(0.7);
                final_score *= recency_multiplier;

                // Simple highlights
                let mut highlights = Vec::new();
                for q in &query_tokens {
                    if doc.subject.to_lowercase().contains(q) {
                        highlights.push(format!("Found in subject: {}", doc.subject));
                        break;
                    }
                }

                Some(SearchHit {
                    document: doc.clone(),
                    score: final_score,
                    highlights,
                })
            })
            .collect();

        // Sort descending by relevance score
        filtered_hits.sort_by(|a, b| b.score.partial_cmp(&a.score).unwrap_or(std::cmp::Ordering::Equal));

        let total_hits = filtered_hits.len();
        let offset = query.offset.unwrap_or(0);
        let limit = query.limit.unwrap_or(20);

        let paged_hits = filtered_hits
            .into_iter()
            .skip(offset)
            .take(limit)
            .collect();

        SearchResponse {
            query: query.query.clone(),
            total_hits,
            took_micros: start.elapsed().as_micros(),
            hits: paged_hits,
        }
    }

    pub fn stats(&self) -> (usize, usize, usize) {
        (self.documents.len(), self.user_docs.len(), self.postings.len())
    }
}

pub struct SearchEngineState {
    pub index: RwLock<InvertedIndex>,
    pub start_time: std::time::Instant,
}

impl SearchEngineState {
    pub fn new() -> Self {
        Self {
            index: RwLock::new(InvertedIndex::new()),
            start_time: std::time::Instant::now(),
        }
    }
}
