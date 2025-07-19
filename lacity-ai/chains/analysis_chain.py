"""
Complaint Analysis Chain using LCEL

Educational Focus:
- LCEL (LangChain Expression Language) basics
- Chain composition with | operator
- Output parsing and validation
- Error handling in chains
- Real-world chain building patterns
"""

import json
from typing import Dict, Any, Optional
from langchain.schema.output_parser import StrOutputParser
from langchain.schema.runnable import RunnablePassthrough, RunnableLambda
from langchain_core.messages import SystemMessage, HumanMessage
import structlog

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from models.openai_client import OpenAIClient
from prompts.templates import ComplaintAnalysisTemplate
from prompts.few_shot_examples import FewShotExamples
from prompts.system_prompts import SystemPrompts
from config import config

logger = structlog.get_logger(__name__)


class ComplaintAnalysisChain:
    """
    LCEL chain for analyzing NYC 311 complaints
    
    Chain Structure:
    Input → Prompt Assembly → Few-Shot Examples → LLM → JSON Parser → Validation → Output
    
    Educational Value:
    - Shows step-by-step LCEL chain building
    - Demonstrates prompt engineering with examples
    - Includes output validation and error handling
    - Real-world production patterns
    """
    
    def __init__(self):
        """Initialize the complaint analysis chain"""
        # Initialize components
        self.openai_client = OpenAIClient()
        self.prompt_template = ComplaintAnalysisTemplate()
        self.few_shot_examples = FewShotExamples()
        
        # Build the LCEL chain
        self.chain = self._build_chain()
        
        logger.info("ComplaintAnalysisChain initialized")
    
    def _build_chain(self):
        """
        Build LCEL chain using | operator composition
        
        This demonstrates the power of LCEL for readable, composable chains
        """
        logger.info("Building LCEL analysis chain")
        
        # Step 1: Input preprocessing and prompt assembly
        # RunnablePassthrough allows data to flow through unchanged
        # RunnableLambda wraps custom functions
        prompt_assembly = (
            RunnablePassthrough.assign(
                # Add system prompt to input
                system_prompt=RunnableLambda(lambda x: SystemPrompts.get_system_prompt('analyst')),
                
                # Add few-shot examples based on similar risk patterns  
                few_shot_examples=RunnableLambda(self._get_relevant_examples),
                
                # Format the main analysis prompt
                analysis_prompt=RunnableLambda(self._format_analysis_prompt)
            )
        )
        
        # Step 2: Create message structure for chat model
        message_formatting = RunnableLambda(self._format_messages)
        
        # Step 3: LLM invocation
        llm_call = self.openai_client.chat_client
        
        # Step 4: Output parsing
        output_parser = StrOutputParser()
        
        # Step 5: JSON validation and cleanup
        json_validator = RunnableLambda(self._validate_and_parse_json)
        
        # Step 6: Final validation and scoring
        final_validator = RunnableLambda(self._validate_analysis_output)
        
        # LCEL Chain Composition using | operator
        # Each step processes the output of the previous step
        chain = (
            prompt_assembly           # Input dict → Enhanced dict with prompts
            | message_formatting      # Enhanced dict → List of messages  
            | llm_call               # Messages → AI response
            | output_parser          # Response → String
            | json_validator         # String → Parsed JSON dict
            | final_validator        # JSON dict → Validated analysis dict
        )
        
        logger.info("LCEL analysis chain built successfully")
        return chain
    
    def _get_relevant_examples(self, input_data: Dict[str, Any]) -> str:
        """
        Select relevant few-shot examples based on input complaint
        
        This educational function shows how to:
        - Dynamically select examples based on input
        - Format examples for prompt inclusion
        - Balance example relevance vs diversity
        """
        complaint_type = input_data.get('type', '').lower()
        
        # Select examples based on complaint type patterns
        if any(keyword in complaint_type for keyword in ['gas', 'leak', 'emergency', 'structural']):
            examples = self.few_shot_examples.get_examples_by_risk_level('high')[:2]
        elif any(keyword in complaint_type for keyword in ['water', 'heat', 'traffic']):
            examples = self.few_shot_examples.get_examples_by_risk_level('medium')[:2]
        else:
            examples = self.few_shot_examples.get_random_examples(3)
        
        formatted_examples = self.few_shot_examples.format_examples_for_prompt(examples)
        
        logger.debug("Selected few-shot examples",
                    complaint_type=complaint_type,
                    example_count=len(examples))
        
        return formatted_examples
    
    def _format_analysis_prompt(self, input_data: Dict[str, Any]) -> str:
        """Format the main analysis prompt with complaint data"""
        return self.prompt_template.format_prompt(input_data)
    
    def _format_messages(self, input_data: Dict[str, Any]) -> list:
        """
        Format input data into message structure for chat model
        
        Educational Note:
        Chat models expect a specific message format with roles (system, human, assistant)
        """
        system_prompt = input_data['system_prompt']
        few_shot_examples = input_data['few_shot_examples']
        analysis_prompt = input_data['analysis_prompt']
        
        # Combine system prompt with examples
        full_system_prompt = f"{system_prompt}\n\nHere are some examples of proper analysis:\n\n{few_shot_examples}"
        
        messages = [
            SystemMessage(content=full_system_prompt),
            HumanMessage(content=analysis_prompt)
        ]
        
        logger.debug("Formatted messages for LLM",
                    message_count=len(messages),
                    system_prompt_length=len(full_system_prompt),
                    human_prompt_length=len(analysis_prompt))
        
        return messages
    
    def _validate_and_parse_json(self, llm_output: str) -> Dict[str, Any]:
        """
        Parse and validate JSON output from LLM
        
        Educational Focus:
        - Robust JSON parsing with error handling
        - Common LLM output issues and solutions
        - Fallback strategies for malformed responses
        """
        if not llm_output or not llm_output.strip():
            raise ValueError("Empty response from LLM")
        
        # Clean up common LLM output issues
        cleaned_output = llm_output.strip()
        
        # Remove markdown code blocks if present
        if cleaned_output.startswith('```'):
            lines = cleaned_output.split('\n')
            # Remove first and last lines if they're markdown markers
            if lines[0].startswith('```') and lines[-1].strip() == '```':
                cleaned_output = '\n'.join(lines[1:-1])
            elif lines[0].startswith('```json'):
                cleaned_output = '\n'.join(lines[1:-1])
        
        # Find JSON content if embedded in text
        if '{' in cleaned_output and '}' in cleaned_output:
            start_idx = cleaned_output.find('{')
            end_idx = cleaned_output.rfind('}') + 1
            cleaned_output = cleaned_output[start_idx:end_idx]
        
        try:
            parsed_json = json.loads(cleaned_output)
            
            logger.debug("Successfully parsed JSON output",
                        original_length=len(llm_output),
                        cleaned_length=len(cleaned_output))
            
            return parsed_json
            
        except json.JSONDecodeError as e:
            logger.error("Failed to parse JSON from LLM output",
                        original_output=llm_output[:200],
                        cleaned_output=cleaned_output[:200],
                        error=str(e))
            
            # Return fallback structure
            return self._create_fallback_analysis(llm_output)
    
    def _validate_analysis_output(self, parsed_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Validate and normalize the parsed analysis output
        
        Educational Focus:
        - Output validation patterns
        - Data normalization and sanitization
        - Error recovery strategies
        """
        validated = {}
        
        # Validate risk_score (required, 0.0-1.0)
        risk_score = parsed_data.get('risk_score', 0.0)
        try:
            risk_score = float(risk_score)
            validated['risk_score'] = max(0.0, min(1.0, risk_score))
        except (ValueError, TypeError):
            logger.warning("Invalid risk_score, using default",
                          original_value=risk_score)
            validated['risk_score'] = 0.5
        
        # Validate category (required, string)
        category = parsed_data.get('category', 'General')
        validated['category'] = str(category) if category else 'General'
        
        # Validate summary (required, string)
        summary = parsed_data.get('summary', 'Analysis completed')
        validated['summary'] = str(summary) if summary else 'Analysis completed'
        
        # Validate tags (optional, list of strings)
        tags = parsed_data.get('tags', [])
        if isinstance(tags, list):
            validated['tags'] = [str(tag) for tag in tags if tag]
        else:
            validated['tags'] = []
        
        # Add metadata
        validated['analysis_method'] = 'lcel_chain'
        validated['model_used'] = config.OPENAI_MODEL
        
        logger.debug("Analysis output validated",
                    risk_score=validated['risk_score'],
                    category=validated['category'],
                    tag_count=len(validated['tags']))
        
        return validated
    
    def _create_fallback_analysis(self, original_output: str) -> Dict[str, Any]:
        """
        Create fallback analysis when JSON parsing fails
        
        Educational Note:
        Always have fallback strategies for production systems
        """
        logger.warning("Creating fallback analysis due to parsing failure")
        
        # Extract basic info from text if possible
        risk_score = 0.5  # Default medium risk
        category = "General"
        
        # Simple heuristics for fallback analysis
        lower_output = original_output.lower()
        if any(word in lower_output for word in ['emergency', 'critical', 'urgent', 'danger']):
            risk_score = 0.8
            category = "Public Safety"
        elif any(word in lower_output for word in ['infrastructure', 'water', 'gas', 'structural']):
            risk_score = 0.6
            category = "Infrastructure"
        
        return {
            'risk_score': risk_score,
            'category': category,
            'summary': f"Fallback analysis created. Original response: {original_output[:100]}...",
            'tags': ['fallback', 'needs-review'],
            'analysis_method': 'fallback',
            'original_output': original_output
        }
    
    def analyze_complaint(self, complaint_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Main entry point for complaint analysis
        
        Args:
            complaint_data: Dictionary containing complaint information
            
        Returns:
            Analysis results dictionary
            
        Educational Note:
        This is how you invoke LCEL chains - simple method call with input data
        """
        logger.info("Starting complaint analysis",
                   complaint_id=complaint_data.get('id'))
        
        try:
            # Invoke the LCEL chain
            result = self.chain.invoke(complaint_data)
            
            logger.info("Complaint analysis completed successfully",
                       complaint_id=complaint_data.get('id'),
                       risk_score=result.get('risk_score'),
                       category=result.get('category'))
            
            return result
            
        except Exception as e:
            logger.error("Complaint analysis failed",
                        complaint_id=complaint_data.get('id'),
                        error=str(e))
            
            # Return fallback analysis
            return self._create_fallback_analysis(f"Analysis failed: {str(e)}")
    
    def analyze_complaints_batch(self, 
                               complaints: list[Dict[str, Any]],
                               max_concurrent: int = 3) -> list[Dict[str, Any]]:
        """
        Analyze multiple complaints with controlled concurrency
        
        Educational Focus:
        - Batch processing patterns
        - Concurrency control for API rate limits
        - Progress tracking for long operations
        """
        if not complaints:
            return []
        
        logger.info("Starting batch complaint analysis",
                   complaint_count=len(complaints),
                   max_concurrent=max_concurrent)
        
        results = []
        
        # Process in batches to respect rate limits
        for i in range(0, len(complaints), max_concurrent):
            batch = complaints[i:i + max_concurrent]
            
            batch_results = []
            for complaint in batch:
                try:
                    result = self.analyze_complaint(complaint)
                    batch_results.append(result)
                except Exception as e:
                    logger.error("Batch analysis failed for complaint",
                               complaint_id=complaint.get('id'),
                               error=str(e))
                    batch_results.append(self._create_fallback_analysis(str(e)))
            
            results.extend(batch_results)
            
            logger.debug("Batch processed",
                        batch_number=i // max_concurrent + 1,
                        batch_size=len(batch),
                        total_processed=len(results))
        
        logger.info("Batch complaint analysis completed",
                   total_complaints=len(complaints),
                   successful_analyses=len([r for r in results if r.get('analysis_method') != 'fallback']))
        
        return results


# Global chain instance for easy access
complaint_analysis_chain = ComplaintAnalysisChain()