"""
PromptTemplates for LaraCity AI System

Educational Focus:
- Structured prompts for consistent AI responses
- Input variable management
- Template composition patterns
- NYC 311 domain-specific prompting
"""

from langchain.prompts import PromptTemplate, FewShotPromptTemplate
from typing import List, Dict, Any
import structlog

logger = structlog.get_logger(__name__)


class ComplaintAnalysisTemplate:
    """
    PromptTemplate for analyzing NYC 311 complaints
    
    Generates structured analysis including:
    - Risk assessment (0.0 - 1.0 scale)
    - Category classification
    - Summary generation
    - Tag extraction
    """
    
    def __init__(self):
        """Initialize complaint analysis template"""
        self.template = PromptTemplate(
            input_variables=[
                "complaint_type", 
                "description", 
                "location", 
                "agency",
                "submitted_at"
            ],
            template=self._get_analysis_template()
        )
        
        logger.info("ComplaintAnalysisTemplate initialized")
    
    def _get_analysis_template(self) -> str:
        """Get the main analysis template string"""
        return """You are an expert municipal complaint analyst for New York City. Analyze this 311 complaint and provide a structured assessment.

COMPLAINT DETAILS:
- Type: {complaint_type}
- Description: {description}
- Location: {location}
- Responsible Agency: {agency}
- Submitted: {submitted_at}

ANALYSIS REQUIREMENTS:
1. Risk Score (0.0-1.0): Assess urgency and potential impact
   - 0.9-1.0: Critical/Emergency (gas leaks, structural damage, immediate danger)
   - 0.7-0.8: High Priority (water outages, heat issues, traffic hazards)
   - 0.4-0.6: Medium Priority (street conditions, sanitation issues)
   - 0.0-0.3: Low Priority (noise complaints, minor parking violations)

2. Category: Classify into primary service area
   - Infrastructure (water, gas, electricity, structural)
   - Transportation (traffic, parking, street conditions)
   - Quality of Life (noise, odors, aesthetics)
   - Public Health (sanitation, food safety, environmental)
   - Public Safety (emergency response, hazards)

3. Summary: 2-3 sentence explanation of the issue and recommended action

4. Tags: 3-5 relevant keywords for search and filtering

RESPONSE FORMAT (JSON):
{{
    "risk_score": 0.0,
    "category": "Category Name",
    "summary": "Clear, actionable summary of the complaint and recommended response.",
    "tags": ["tag1", "tag2", "tag3", "tag4"]
}}

Consider factors like:
- Potential for escalation or spreading
- Impact on public safety and health
- Infrastructure dependencies
- Time-sensitivity of the issue
- Resource requirements for resolution

Provide your analysis:"""
    
    def format_prompt(self, complaint_data: Dict[str, Any]) -> str:
        """
        Format prompt with complaint data
        
        Args:
            complaint_data: Dictionary containing complaint information
            
        Returns:
            Formatted prompt string
        """
        try:
            formatted = self.template.format(
                complaint_type=complaint_data.get('type', 'Unknown'),
                description=complaint_data.get('description', 'No description provided'),
                location=f"{complaint_data.get('borough', 'Unknown')}, {complaint_data.get('address', 'Address not specified')}",
                agency=complaint_data.get('agency', 'Unknown Agency'),
                submitted_at=complaint_data.get('submitted_at', 'Unknown time')
            )
            
            logger.debug("Complaint analysis prompt formatted",
                        complaint_id=complaint_data.get('id'),
                        prompt_length=len(formatted))
            
            return formatted
            
        except Exception as e:
            logger.error("Failed to format complaint analysis prompt",
                        complaint_data=complaint_data,
                        error=str(e))
            raise


class QuestionAnsweringTemplate:
    """
    PromptTemplate for RAG-based question answering about complaints
    
    Uses retrieved complaint context to answer user questions
    """
    
    def __init__(self):
        """Initialize question answering template"""
        self.template = PromptTemplate(
            input_variables=[
                "question",
                "context_complaints",
                "conversation_history"
            ],
            template=self._get_qa_template()
        )
        
        logger.info("QuestionAnsweringTemplate initialized")
    
    def _get_qa_template(self) -> str:
        """Get the Q&A template string"""
        return """You are a helpful NYC 311 data assistant. Answer the user's question based on the provided complaint data context.

CONVERSATION HISTORY:
{conversation_history}

USER QUESTION:
{question}

RELEVANT COMPLAINT DATA:
{context_complaints}

INSTRUCTIONS:
1. Answer the question accurately using only the provided complaint data
2. If you cannot answer based on the available data, say so clearly
3. Provide specific numbers, locations, and details when available
4. Suggest follow-up questions or clarifications if helpful
5. Be concise but informative
6. Use a helpful, professional tone

ANSWER:"""
    
    def format_prompt(self, 
                     question: str,
                     context_complaints: List[Dict[str, Any]],
                     conversation_history: str = "") -> str:
        """
        Format Q&A prompt with question and context
        
        Args:
            question: User's question
            context_complaints: List of relevant complaints for context
            conversation_history: Previous conversation context
            
        Returns:
            Formatted prompt string
        """
        # Format complaint context
        context_text = self._format_complaint_context(context_complaints)
        
        try:
            formatted = self.template.format(
                question=question,
                context_complaints=context_text,
                conversation_history=conversation_history or "No previous conversation."
            )
            
            logger.debug("Q&A prompt formatted",
                        question_length=len(question),
                        context_complaints=len(context_complaints),
                        prompt_length=len(formatted))
            
            return formatted
            
        except Exception as e:
            logger.error("Failed to format Q&A prompt",
                        question=question[:100],
                        error=str(e))
            raise
    
    def _format_complaint_context(self, complaints: List[Dict[str, Any]]) -> str:
        """Format complaint data for context"""
        if not complaints:
            return "No relevant complaints found in the database."
        
        context_parts = []
        for i, complaint in enumerate(complaints, 1):
            context_parts.append(f"""
Complaint #{i}:
- ID: {complaint.get('id', 'Unknown')}
- Type: {complaint.get('type', 'Unknown')}
- Description: {complaint.get('description', 'No description')}
- Location: {complaint.get('borough', 'Unknown')}, {complaint.get('address', 'Address not specified')}
- Agency: {complaint.get('agency', 'Unknown')}
- Status: {complaint.get('status', 'Unknown')}
- Submitted: {complaint.get('submitted_at', 'Unknown')}
""")
        
        return "\n".join(context_parts)


class SummarizationTemplate:
    """
    PromptTemplate for summarizing complaint data and trends
    """
    
    def __init__(self):
        """Initialize summarization template"""
        self.template = PromptTemplate(
            input_variables=[
                "complaints_data",
                "summary_type",
                "time_period"
            ],
            template=self._get_summary_template()
        )
        
        logger.info("SummarizationTemplate initialized")
    
    def _get_summary_template(self) -> str:
        """Get the summarization template string"""
        return """Analyze and summarize the following NYC 311 complaint data.

SUMMARY TYPE: {summary_type}
TIME PERIOD: {time_period}

COMPLAINT DATA:
{complaints_data}

Please provide a comprehensive summary including:

1. OVERVIEW
   - Total number of complaints
   - Most common complaint types
   - Geographic distribution (boroughs)

2. KEY TRENDS
   - Notable patterns or increases
   - High-priority or urgent issues
   - Service delivery insights

3. AGENCY PERFORMANCE
   - Most active agencies
   - Resolution patterns
   - Areas needing attention

4. RECOMMENDATIONS
   - Priority actions needed
   - Resource allocation suggestions
   - Process improvements

Format your response clearly with bullet points and specific data points where available."""
    
    def format_prompt(self,
                     complaints_data: str,
                     summary_type: str = "General Summary",
                     time_period: str = "Recent Period") -> str:
        """
        Format summarization prompt
        
        Args:
            complaints_data: Formatted complaint data string
            summary_type: Type of summary requested
            time_period: Time period being analyzed
            
        Returns:
            Formatted prompt string
        """
        try:
            formatted = self.template.format(
                complaints_data=complaints_data,
                summary_type=summary_type,
                time_period=time_period
            )
            
            logger.debug("Summary prompt formatted",
                        summary_type=summary_type,
                        data_length=len(complaints_data),
                        prompt_length=len(formatted))
            
            return formatted
            
        except Exception as e:
            logger.error("Failed to format summary prompt",
                        summary_type=summary_type,
                        error=str(e))
            raise


# Template instances for easy access
complaint_analysis_template = ComplaintAnalysisTemplate()
question_answering_template = QuestionAnsweringTemplate()
summarization_template = SummarizationTemplate()