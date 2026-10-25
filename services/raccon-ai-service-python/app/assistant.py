import re
from typing import List, Dict, Any

class GrokEmailAssistant:
    """
    AI Assistant providing instant summaries, smart quick-replies,
    and recommendation feed ranking (modeled after X algorithm).
    """

    def summarize(self, subject: str, body: str) -> Dict[str, Any]:
        # Clean text
        clean_body = re.sub(r'<[^>]+>', ' ', body)
        clean_body = re.sub(r'\s+', ' ', clean_body).strip()
        sentences = [s.strip() for s in re.split(r'[.!?]+', clean_body) if len(s.strip()) > 15]

        # Extract high-value action items or first 3 sentences
        key_points = []
        action_words = ['please', 'need', 'review', 'confirm', 'attached', 'update', 'status', 'meeting', 'deadline']
        for s in sentences:
            if any(w in s.lower() for w in action_words):
                key_points.append(s)
                if len(key_points) >= 3:
                    break

        if not key_points:
            key_points = sentences[:3]

        if not key_points:
            key_points = [clean_body[:120] + "..."]

        # Determine sentiment & urgency
        urgency = "Normal"
        if re.search(r'\burgent|asap|immediate|today|deadline\b', clean_body.lower()):
            urgency = "High"

        return {
            "summary_bullets": key_points,
            "estimated_read_time_seconds": max(15, len(clean_body.split()) // 3),
            "urgency": urgency,
            "sentiment": "Neutral" if urgency == "Normal" else "Action Required"
        }

    def generate_smart_replies(self, subject: str, body: str) -> List[str]:
        text = f"{subject} {body}".lower()

        if any(w in text for w in ['when can you', 'what time', 'available for']):
            return [
                "I'm available today afternoon. Let me know what time works best!",
                "Thanks for reaching out! Let me check my calendar and get back to you shortly.",
                "Unfortunately I'm fully booked today, but tomorrow morning works great for me."
            ]
        elif any(w in text for w in ['please review', 'feedback', 'take a look', 'attached']):
            return [
                "Looks great to me! Everything is approved on my end.",
                "Thank you! I will review the details carefully and share feedback soon.",
                "Thanks for sending. Could you please clarify point #2 before we proceed?"
            ]
        elif any(w in text for w in ['confirm', 'verify', 'ok with you']):
            return [
                "Confirmed! That sounds perfect.",
                "Yes, I confirm. Please go ahead.",
                "Let's make a slight adjustment before finalizing."
            ]
        else:
            return [
                "Thank you for the update! 🦝",
                "Received, thanks! I will follow up shortly.",
                "Got it. Let me know if you need any additional info from me."
            ]

    def rank_email_feed(self, items: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Ranking algorithm adapting X's 'For You' candidate scoring to Mail:
        Score = (is_starred * 2.0) + (is_unread * 1.5) + (recency_score) + (vip_sender_score)
        """
        scored_items = []
        for item in items:
            score = 1.0
            if item.get("is_starred"):
                score += 2.0
            if item.get("is_unread"):
                score += 1.5
            if item.get("has_attachments"):
                score += 0.5

            item_copy = dict(item)
            item_copy["recommendation_score"] = round(score, 3)
            scored_items.append(item_copy)

        scored_items.sort(key=lambda x: x["recommendation_score"], reverse=True)
        return scored_items
