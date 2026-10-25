import re
from typing import Dict, Any

class EmailCategoryClassifier:
    """
    High-performance semantic classifier for triage & inbox organization.
    Classifies messages into: Primary, Social, Updates, Promotions, Spam, Forum.
    """

    SPAM_SIGNALS = [
        r'\bcongratulations you won\b', r'\bclaim your prize\b', r'\bwire transfer\b',
        r'\bbitcoin wallet\b', r'\b100% free\b', r'\bviagra\b', r'\burgent inheritance\b',
        r'\bdouble your income\b', r'\bunsecured loan\b'
    ]

    PROMO_SIGNALS = [
        r'\bdiscount\b', r'\bsale\b', r'\bcoupon\b', r'\boff your order\b',
        r'\bexclusive offer\b', r'\bfree shipping\b', r'\bshop now\b', r'\bpromo code\b'
    ]

    SOCIAL_SIGNALS = [
        r'\bmentioned you\b', r'\bfollowed you\b', r'\bnew connection\b',
        r'\bliked your post\b', r'\bcommented on\b', r'\bfriend request\b', r'twitter|instagram|linkedin|facebook'
    ]

    UPDATES_SIGNALS = [
        r'\binvoice\b', r'\breceipt\b', r'\border confirmation\b', r'\bsecurity alert\b',
        r'\bpassword reset\b', r'\bpayment received\b', r'\bshipping update\b', r'\bstatement\b'
    ]

    FORUM_SIGNALS = [
        r'\bnew topic\b', r'\bthread reply\b', r'\bcommunity discussion\b',
        r'\bdigest\b', r'\braccon stories\b', r'\braccon forum\b'
    ]

    def classify(self, sender: str, subject: str, body: str) -> Dict[str, Any]:
        combined_text = f"{sender} {subject} {body}".lower()

        # 1. Check Spam First
        for pattern in self.SPAM_SIGNALS:
            if re.search(pattern, combined_text):
                return {
                    "category": "Spam",
                    "confidence": 0.96,
                    "reason": f"Matched spam pattern '{pattern}'"
                }

        # 2. Check Social
        for pattern in self.SOCIAL_SIGNALS:
            if re.search(pattern, combined_text):
                return {
                    "category": "Social",
                    "confidence": 0.91,
                    "reason": f"Matched social signal '{pattern}'"
                }

        # 3. Check Promotions
        for pattern in self.PROMO_SIGNALS:
            if re.search(pattern, combined_text):
                return {
                    "category": "Promotions",
                    "confidence": 0.88,
                    "reason": f"Matched promotional keywords"
                }

        # 4. Check Updates & Billing
        for pattern in self.UPDATES_SIGNALS:
            if re.search(pattern, combined_text):
                return {
                    "category": "Updates",
                    "confidence": 0.94,
                    "reason": f"System or transactional update"
                }

        # 5. Check Community/Forums
        for pattern in self.FORUM_SIGNALS:
            if re.search(pattern, combined_text):
                return {
                    "category": "Forums",
                    "confidence": 0.89,
                    "reason": f"Community or forum notification"
                }

        # Default to Primary
        return {
            "category": "Primary",
            "confidence": 0.98,
            "reason": "Direct human-to-human correspondence"
        }
