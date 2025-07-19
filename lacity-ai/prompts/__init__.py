"""
Prompts module for LaraCity AI
PromptTemplates and few-shot learning examples
"""

from .templates import ComplaintAnalysisTemplate, QuestionAnsweringTemplate, SummarizationTemplate
from .few_shot_examples import FewShotExamples
from .system_prompts import SystemPrompts

__all__ = [
    "ComplaintAnalysisTemplate", 
    "QuestionAnsweringTemplate", 
    "SummarizationTemplate",
    "FewShotExamples", 
    "SystemPrompts"
]