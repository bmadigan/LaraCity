"""
Models module for LaraCity AI
OpenAI client setup and embedding generation
"""

from .openai_client import OpenAIClient
from .embeddings import EmbeddingGenerator

__all__ = ["OpenAIClient", "EmbeddingGenerator"]