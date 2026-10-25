import os
import requests
from typing import List, Optional
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

from .classifier import EmailCategoryClassifier
from .assistant import GrokEmailAssistant

app = FastAPI(
    title="🦝 Raccon AI & Recommendation Service",
    description="Python AI service powering smart categorization, summarization, and feed ranking (modeled after X/Grok stack)",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

classifier = EmailCategoryClassifier()
assistant = GrokEmailAssistant()

RUST_SEARCH_URL = os.getenv("RUST_SEARCH_URL", "http://localhost:8088")

class CategorizeRequest(BaseModel):
    sender: str
    subject: str
    body: str

class SummarizeRequest(BaseModel):
    subject: str
    body: str

class SmartReplyRequest(BaseModel):
    subject: str
    body: str

class FeedItem(BaseModel):
    id: str
    subject: str
    sender: str
    is_unread: bool = False
    is_starred: bool = False
    has_attachments: bool = False
    timestamp: Optional[int] = None

class FeedRankRequest(BaseModel):
    items: List[FeedItem]

@app.get("/health")
def health_check():
    return {
        "status": "healthy",
        "service": "raccon-ai-service-python",
        "rust_engine_target": RUST_SEARCH_URL
    }

@app.post("/ai/v1/categorize")
def categorize_email(req: CategorizeRequest):
    return classifier.classify(req.sender, req.subject, req.body)

@app.post("/ai/v1/summarize")
def summarize_email(req: SummarizeRequest):
    return assistant.summarize(req.subject, req.body)

@app.post("/ai/v1/smart-reply")
def get_smart_replies(req: SmartReplyRequest):
    replies = assistant.generate_smart_replies(req.subject, req.body)
    return {"smart_replies": replies}

@app.post("/ai/v1/rank-feed")
def rank_feed(req: FeedRankRequest):
    raw_dicts = [item.dict() for item in req.items]
    ranked = assistant.rank_email_feed(raw_dicts)
    return {"ranked_items": ranked}

@app.get("/ai/v1/search-with-ai")
def search_and_augment(user_id: str, query: str):
    """
    Calls the high-speed Rust search engine, then augments top hits with AI urgency & categorization
    """
    try:
        rust_res = requests.get(
            f"{RUST_SEARCH_URL}/api/v1/search",
            params={"user_id": user_id, "query": query, "limit": 10},
            timeout=3.0
        )
        if rust_res.status_code != 200:
            raise HTTPException(status_code=502, detail="Rust search engine returned non-200")
        
        data = rust_res.json()
        augmented_hits = []
        for hit in data.get("hits", []):
            doc = hit.get("document", {})
            cat = classifier.classify(doc.get("sender", ""), doc.get("subject", ""), doc.get("snippet", ""))
            hit["ai_category"] = cat["category"]
            augmented_hits.append(hit)

        data["hits"] = augmented_hits
        data["ai_augmented"] = True
        return data
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Search pipeline error: {str(e)}")
