"""
LaraCity AI Configuration Management
Centralized settings for LangChain RAG system
"""

import os
from typing import Optional

# Try to load environment variables from Laravel .env file
try:
    from dotenv import load_dotenv
    load_dotenv(dotenv_path="../.env")
    DOTENV_AVAILABLE = True
except ImportError:
    DOTENV_AVAILABLE = False
    print("⚠️  python-dotenv not available, using system environment variables only")


class LaraCityConfig:
    """Centralized configuration management for LaraCity AI system"""
    
    # OpenAI Configuration
    OPENAI_API_KEY: Optional[str] = os.getenv("OPENAI_API_KEY")
    OPENAI_MODEL: str = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
    OPENAI_EMBEDDING_MODEL: str = os.getenv("OPENAI_EMBEDDING_MODEL", "text-embedding-3-small")
    
    # Model Parameters
    TEMPERATURE: float = float(os.getenv("OPENAI_TEMPERATURE", "0.1"))
    MAX_TOKENS: int = int(os.getenv("OPENAI_MAX_TOKENS", "2000"))
    REQUEST_TIMEOUT: int = int(os.getenv("OPENAI_TIMEOUT", "30"))
    
    # Vector Search Configuration
    EMBEDDING_DIMENSION: int = int(os.getenv("EMBEDDING_DIMENSION", "1536"))
    VECTOR_SEARCH_K: int = int(os.getenv("VECTOR_SEARCH_K", "5"))
    SIMILARITY_THRESHOLD: float = float(os.getenv("SIMILARITY_THRESHOLD", "0.8"))
    
    # RAG Configuration
    RAG_CHUNK_SIZE: int = int(os.getenv("RAG_CHUNK_SIZE", "1000"))
    RAG_CHUNK_OVERLAP: int = int(os.getenv("RAG_CHUNK_OVERLAP", "200"))
    VECTOR_STORE_PATH: str = os.getenv("VECTOR_STORE_PATH", "./data/vector_store")
    EMBEDDING_BATCH_SIZE: int = int(os.getenv("EMBEDDING_BATCH_SIZE", "50"))
    
    # Database Configuration (PostgreSQL with pgvector)
    DATABASE_URL: Optional[str] = os.getenv("DATABASE_URL")
    DB_HOST: str = os.getenv("DB_HOST", "127.0.0.1")
    DB_PORT: int = int(os.getenv("DB_PORT", "5432"))
    DB_NAME: str = os.getenv("DB_DATABASE", "laracity")
    DB_USER: str = os.getenv("DB_USERNAME", "postgres")
    DB_PASSWORD: str = os.getenv("DB_PASSWORD", "postgres")
    
    # Complaint Analysis Configuration
    ESCALATION_THRESHOLD: float = float(os.getenv("COMPLAINT_ESCALATE_THRESHOLD", "0.7"))
    
    # Risk Classification Thresholds
    RISK_LEVELS = {
        "low": {"min": 0.0, "max": 0.4},
        "medium": {"min": 0.4, "max": 0.7},
        "high": {"min": 0.7, "max": 1.0}
    }
    
    # High-Risk Complaint Types (for few-shot learning)
    HIGH_RISK_TYPES = [
        "Gas Leak",
        "Water Main Break", 
        "Structural Damage",
        "Electrical Hazard",
        "Emergency Response",
        "Fire Safety",
        "Chemical Spill"
    ]
    
    # Medium-Risk Complaint Types
    MEDIUM_RISK_TYPES = [
        "Water System",
        "Heat/Hot Water", 
        "Plumbing",
        "Street Condition",
        "Traffic Signal",
        "Sanitation Condition"
    ]
    
    # Low-Risk Complaint Types
    LOW_RISK_TYPES = [
        "Noise - Street/Sidewalk",
        "Illegal Parking",
        "Graffiti",
        "Litter Basket",
        "Animal Noise"
    ]
    
    # Logging Configuration
    LOG_LEVEL: str = os.getenv("LOG_LEVEL", "INFO")
    
    @classmethod
    def validate_config(cls) -> list[str]:
        """Validate required configuration and return list of errors"""
        errors = []
        
        if not cls.OPENAI_API_KEY:
            errors.append("OPENAI_API_KEY is required")
            
        if cls.EMBEDDING_DIMENSION not in [512, 1536, 3072]:
            errors.append(f"EMBEDDING_DIMENSION {cls.EMBEDDING_DIMENSION} not supported by OpenAI")
            
        if cls.TEMPERATURE < 0 or cls.TEMPERATURE > 2:
            errors.append(f"TEMPERATURE {cls.TEMPERATURE} must be between 0 and 2")
            
        return errors
    
    @classmethod
    def get_database_url(cls) -> str:
        """Get properly formatted database URL for pgvector"""
        if cls.DATABASE_URL:
            return cls.DATABASE_URL
        
        return f"postgresql://{cls.DB_USER}:{cls.DB_PASSWORD}@{cls.DB_HOST}:{cls.DB_PORT}/{cls.DB_NAME}"
    
    @classmethod
    def get_risk_level(cls, score: float) -> str:
        """Get risk level string from numeric score"""
        for level, thresholds in cls.RISK_LEVELS.items():
            if thresholds["min"] <= score < thresholds["max"]:
                return level
        return "high" if score >= 0.7 else "low"


# Global config instance
config = LaraCityConfig()

# Validate configuration on import
config_errors = config.validate_config()
if config_errors:
    print(f"⚠️  Configuration errors: {', '.join(config_errors)}")