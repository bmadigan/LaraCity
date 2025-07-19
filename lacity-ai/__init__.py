"""
LaraCity AI: LangChain RAG System for Municipal Complaint Management

A comprehensive tutorial implementation demonstrating:
- OpenAI integration with error handling
- PromptTemplates for structured AI responses  
- Few-Shot learning with real NYC 311 data
- LCEL (LangChain Expression Language) chains
- RAG system with vector search
- Chat agents with conversation memory

Educational Focus: Beginner-friendly LangChain patterns
"""

__version__ = "1.0.0"
__author__ = "LaraCity Development Team"

from .config import config, LaraCityConfig

__all__ = ["config", "LaraCityConfig"]