"""
Few-Shot Learning Examples for NYC 311 Complaint Analysis

Educational Focus:
- Example-driven prompt engineering
- Real NYC 311 data patterns
- Risk assessment training examples
- Consistent output formatting
"""

from typing import List, Dict, Any
import structlog

logger = structlog.get_logger(__name__)


class FewShotExamples:
    """
    Curated examples for few-shot learning with real NYC 311 patterns
    
    These examples train the model to:
    - Assess risk levels accurately
    - Categorize complaints consistently
    - Generate appropriate summaries
    - Extract relevant tags
    """
    
    def __init__(self):
        """Initialize with comprehensive example set"""
        self.examples = self._load_examples()
        
        logger.info("FewShotExamples initialized",
                   example_count=len(self.examples))
    
    def _load_examples(self) -> List[Dict[str, Any]]:
        """Load curated few-shot examples"""
        return [
            # HIGH RISK EXAMPLES (0.8-1.0)
            {
                "input": {
                    "complaint_type": "Gas Leak",
                    "description": "Strong gas smell reported at residential building, multiple residents affected",
                    "location": "MANHATTAN, 425 East 85th Street",
                    "agency": "FDNY",
                    "submitted_at": "2024-01-15 09:30:00"
                },
                "output": {
                    "risk_score": 0.95,
                    "category": "Public Safety",
                    "summary": "Critical gas leak at residential building requires immediate emergency response. Multiple residents affected, potential for explosion or health emergency. FDNY dispatch and building evacuation protocols needed.",
                    "tags": ["emergency", "gas-leak", "residential", "evacuation", "fdny"]
                }
            },
            {
                "input": {
                    "complaint_type": "Water Main Break",
                    "description": "Large water main break flooding street and affecting water pressure in area",
                    "location": "BROOKLYN, Ocean Avenue & Avenue J",
                    "agency": "DEP",
                    "submitted_at": "2024-01-15 14:22:00"
                },
                "output": {
                    "risk_score": 0.85,
                    "category": "Infrastructure",
                    "summary": "Major water main break causing street flooding and service disruption. Immediate repair needed to restore water pressure and prevent further infrastructure damage. Traffic rerouting required.",
                    "tags": ["water-main", "flooding", "infrastructure", "service-disruption", "traffic"]
                }
            },
            {
                "input": {
                    "complaint_type": "Structural Damage",
                    "description": "Cracks in building facade with falling debris, safety concern for pedestrians",
                    "location": "QUEENS, 42-15 Northern Boulevard",
                    "agency": "DOB",
                    "submitted_at": "2024-01-15 11:45:00"
                },
                "output": {
                    "risk_score": 0.88,
                    "category": "Public Safety",
                    "summary": "Structural integrity compromised with falling debris creating immediate pedestrian hazard. Emergency building inspection required, potential sidewalk closure until repairs completed.",
                    "tags": ["structural", "falling-debris", "pedestrian-safety", "building-inspection", "emergency"]
                }
            },
            
            # MEDIUM-HIGH RISK EXAMPLES (0.6-0.7)
            {
                "input": {
                    "complaint_type": "Heat/Hot Water",
                    "description": "No heat or hot water for 3 days, elderly residents in building",
                    "location": "BRONX, 1650 Grand Concourse",
                    "agency": "HPD",
                    "submitted_at": "2024-01-15 08:15:00"
                },
                "output": {
                    "risk_score": 0.75,
                    "category": "Infrastructure",
                    "summary": "Extended heating outage affecting vulnerable population requires priority response. 3-day duration creates health risk, especially for elderly residents. Emergency heating assistance and landlord compliance action needed.",
                    "tags": ["heat-outage", "elderly", "vulnerable-population", "landlord-violation", "health-risk"]
                }
            },
            {
                "input": {
                    "complaint_type": "Traffic Signal Condition",
                    "description": "Traffic light not working at major intersection, cars running red light",
                    "location": "MANHATTAN, Broadway & 125th Street",
                    "agency": "DOT",
                    "submitted_at": "2024-01-15 16:20:00"
                },
                "output": {
                    "risk_score": 0.72,
                    "category": "Transportation",
                    "summary": "Non-functioning traffic signal at major intersection creates accident risk and traffic congestion. Immediate repair or temporary traffic control needed to prevent collisions.",
                    "tags": ["traffic-signal", "major-intersection", "accident-risk", "traffic-control", "urgent-repair"]
                }
            },
            
            # MEDIUM RISK EXAMPLES (0.4-0.6)
            {
                "input": {
                    "complaint_type": "Street Condition",
                    "description": "Large pothole on residential street causing vehicle damage",
                    "location": "QUEENS, 34th Avenue near 82nd Street",
                    "agency": "DOT",
                    "submitted_at": "2024-01-15 13:10:00"
                },
                "output": {
                    "risk_score": 0.55,
                    "category": "Infrastructure",
                    "summary": "Significant pothole causing vehicle damage requires prompt road repair. Standard DOT work order for asphalt patching and street restoration within regular maintenance schedule.",
                    "tags": ["pothole", "vehicle-damage", "street-repair", "maintenance", "residential"]
                }
            },
            {
                "input": {
                    "complaint_type": "Sanitation Condition",
                    "description": "Overflowing trash bins attracting rats and creating odor",
                    "location": "BROOKLYN, Prospect Park West & 15th Street",
                    "agency": "DSNY",
                    "submitted_at": "2024-01-15 10:30:00"
                },
                "output": {
                    "risk_score": 0.48,
                    "category": "Public Health",
                    "summary": "Sanitation issue with pest attraction requires increased collection frequency. Standard DSNY response for overflow management and pest control coordination.",
                    "tags": ["sanitation", "overflow", "pest-control", "odor", "collection-schedule"]
                }
            },
            
            # LOW RISK EXAMPLES (0.0-0.3)
            {
                "input": {
                    "complaint_type": "Noise - Street/Sidewalk",
                    "description": "Loud music from street performers in park area during evening",
                    "location": "MANHATTAN, Washington Square Park",
                    "agency": "NYPD",
                    "submitted_at": "2024-01-15 19:45:00"
                },
                "output": {
                    "risk_score": 0.25,
                    "category": "Quality of Life",
                    "summary": "Routine noise complaint about street performers requires standard NYPD response for noise regulation enforcement. Non-emergency community policing issue.",
                    "tags": ["noise", "street-performers", "quality-of-life", "non-emergency", "community-policing"]
                }
            },
            {
                "input": {
                    "complaint_type": "Illegal Parking",
                    "description": "Car parked in bike lane for several hours",
                    "location": "BROOKLYN, 5th Avenue near Prospect Park",
                    "agency": "NYPD",
                    "submitted_at": "2024-01-15 14:00:00"
                },
                "output": {
                    "risk_score": 0.15,
                    "category": "Transportation",
                    "summary": "Minor parking violation in bike lane requires standard ticketing and possible towing. Routine traffic enforcement matter without safety emergency.",
                    "tags": ["illegal-parking", "bike-lane", "traffic-enforcement", "routine", "non-emergency"]
                }
            },
            {
                "input": {
                    "complaint_type": "Graffiti",
                    "description": "Graffiti on side of commercial building, not obscene",
                    "location": "QUEENS, Northern Boulevard commercial area",
                    "agency": "DSNY",
                    "submitted_at": "2024-01-15 12:20:00"
                },
                "output": {
                    "risk_score": 0.20,
                    "category": "Quality of Life",
                    "summary": "Non-offensive graffiti removal request for commercial property. Standard DSNY graffiti removal program, scheduled within regular maintenance cycle.",
                    "tags": ["graffiti", "commercial", "aesthetic", "maintenance", "non-urgent"]
                }
            }
        ]
    
    def get_examples_by_risk_level(self, risk_level: str) -> List[Dict[str, Any]]:
        """
        Get examples filtered by risk level
        
        Args:
            risk_level: 'high', 'medium', or 'low'
            
        Returns:
            List of examples for the specified risk level
        """
        risk_ranges = {
            'high': (0.7, 1.0),
            'medium': (0.4, 0.7),
            'low': (0.0, 0.4)
        }
        
        if risk_level not in risk_ranges:
            raise ValueError(f"Risk level must be one of: {list(risk_ranges.keys())}")
        
        min_risk, max_risk = risk_ranges[risk_level]
        
        filtered_examples = [
            example for example in self.examples
            if min_risk <= example['output']['risk_score'] < max_risk
        ]
        
        logger.debug("Filtered examples by risk level",
                    risk_level=risk_level,
                    example_count=len(filtered_examples))
        
        return filtered_examples
    
    def get_examples_by_category(self, category: str) -> List[Dict[str, Any]]:
        """
        Get examples filtered by category
        
        Args:
            category: Category name to filter by
            
        Returns:
            List of examples for the specified category
        """
        filtered_examples = [
            example for example in self.examples
            if example['output']['category'].lower() == category.lower()
        ]
        
        logger.debug("Filtered examples by category",
                    category=category,
                    example_count=len(filtered_examples))
        
        return filtered_examples
    
    def get_random_examples(self, count: int = 3) -> List[Dict[str, Any]]:
        """
        Get random selection of examples for few-shot prompting
        
        Args:
            count: Number of examples to return
            
        Returns:
            Random selection of examples
        """
        import random
        
        if count >= len(self.examples):
            return self.examples.copy()
        
        selected = random.sample(self.examples, count)
        
        logger.debug("Selected random examples",
                    requested_count=count,
                    selected_count=len(selected))
        
        return selected
    
    def format_example_for_prompt(self, example: Dict[str, Any]) -> str:
        """
        Format a single example for inclusion in few-shot prompt
        
        Args:
            example: Example dictionary with input/output
            
        Returns:
            Formatted example string
        """
        input_data = example['input']
        output_data = example['output']
        
        formatted = f"""
EXAMPLE:
Input:
- Type: {input_data['complaint_type']}
- Description: {input_data['description']}
- Location: {input_data['location']}
- Agency: {input_data['agency']}
- Submitted: {input_data['submitted_at']}

Output:
{{
    "risk_score": {output_data['risk_score']},
    "category": "{output_data['category']}",
    "summary": "{output_data['summary']}",
    "tags": {output_data['tags']}
}}
"""
        return formatted.strip()
    
    def format_examples_for_prompt(self, examples: List[Dict[str, Any]]) -> str:
        """
        Format multiple examples for few-shot prompt
        
        Args:
            examples: List of example dictionaries
            
        Returns:
            Formatted examples string
        """
        if not examples:
            return "No examples available."
        
        formatted_examples = [
            self.format_example_for_prompt(example)
            for example in examples
        ]
        
        return "\n\n".join(formatted_examples)
    
    def get_stats(self) -> Dict[str, Any]:
        """Get statistics about the example set"""
        categories = {}
        risk_levels = {'high': 0, 'medium': 0, 'low': 0}
        
        for example in self.examples:
            # Count categories
            category = example['output']['category']
            categories[category] = categories.get(category, 0) + 1
            
            # Count risk levels
            risk_score = example['output']['risk_score']
            if risk_score >= 0.7:
                risk_levels['high'] += 1
            elif risk_score >= 0.4:
                risk_levels['medium'] += 1
            else:
                risk_levels['low'] += 1
        
        return {
            'total_examples': len(self.examples),
            'categories': categories,
            'risk_levels': risk_levels
        }


# Global examples instance
few_shot_examples = FewShotExamples()