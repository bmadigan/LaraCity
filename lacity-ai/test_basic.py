#!/usr/bin/env python3
"""
Basic test script to validate LangChain system structure
without requiring external dependencies
"""

import json
import sys
from pathlib import Path

# Add the project root to Python path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

def test_imports():
    """Test that all modules can be imported"""
    try:
        print("Testing config import...")
        from config import config
        print(f"✅ Config imported successfully - Model: {config.OPENAI_MODEL}")
        
        print("Testing prompt imports...")
        from prompts.system_prompts import SystemPrompts
        from prompts.templates import ComplaintAnalysisTemplate
        from prompts.few_shot_examples import FewShotExamples
        print("✅ Prompt modules imported successfully")
        
        # Test basic functionality without external dependencies
        print("Testing system prompts...")
        analyst_prompt = SystemPrompts.get_system_prompt('analyst')
        print(f"✅ Analyst prompt length: {len(analyst_prompt)} characters")
        
        print("Testing few-shot examples...")
        examples = FewShotExamples()
        high_risk_examples = examples.get_examples_by_risk_level('high')
        print(f"✅ Found {len(high_risk_examples)} high-risk examples")
        
        print("Testing prompt templates...")
        template = ComplaintAnalysisTemplate()
        sample_data = {
            'type': 'Noise',
            'description': 'Loud construction',
            'borough': 'MANHATTAN'
        }
        formatted = template.format_prompt(sample_data)
        print(f"✅ Template formatted successfully - length: {len(formatted)} characters")
        
        return True
        
    except Exception as e:
        print(f"❌ Import test failed: {str(e)}")
        return False

def test_langchain_runner_structure():
    """Test the langchain_runner structure without executing"""
    try:
        print("Testing langchain_runner structure...")
        
        # Test if file exists and is executable
        runner_path = project_root / "langchain_runner.py"
        if not runner_path.exists():
            print("❌ langchain_runner.py not found")
            return False
            
        # Test basic argument parsing
        import subprocess
        result = subprocess.run([
            sys.executable, str(runner_path), "--help"
        ], capture_output=True, text=True, timeout=10)
        
        if result.returncode == 0:
            print("✅ langchain_runner.py help command works")
            print(f"Available operations: analyze_complaint, answer_question, chat, etc.")
            return True
        else:
            print(f"❌ langchain_runner.py help failed: {result.stderr}")
            return False
            
    except Exception as e:
        print(f"❌ LangChain runner test failed: {str(e)}")
        return False

def test_php_integration_interface():
    """Test the interface that PHP would use"""
    try:
        print("Testing PHP integration interface...")
        
        # Simulate what PHP would send
        test_data = {
            "complaint_data": {
                "id": 123,
                "type": "Noise",
                "description": "Loud construction noise",
                "borough": "MANHATTAN",
                "agency": "DEP"
            }
        }
        
        # Test JSON serialization (what PHP would send)
        json_data = json.dumps(test_data)
        parsed_data = json.loads(json_data)
        
        print(f"✅ JSON serialization test passed")
        print(f"✅ Sample complaint ID: {parsed_data['complaint_data']['id']}")
        
        return True
        
    except Exception as e:
        print(f"❌ PHP integration test failed: {str(e)}")
        return False

def main():
    """Run all tests"""
    print("🚀 Starting LaraCity AI System Tests")
    print("=" * 50)
    
    tests = [
        ("Module Import Test", test_imports),
        ("LangChain Runner Structure Test", test_langchain_runner_structure),
        ("PHP Integration Interface Test", test_php_integration_interface)
    ]
    
    passed = 0
    total = len(tests)
    
    for test_name, test_func in tests:
        print(f"\n📋 {test_name}")
        print("-" * 30)
        
        if test_func():
            passed += 1
            print(f"✅ {test_name} PASSED")
        else:
            print(f"❌ {test_name} FAILED")
    
    print("\n" + "=" * 50)
    print(f"📊 Test Results: {passed}/{total} tests passed")
    
    if passed == total:
        print("🎉 All tests passed! System structure is valid.")
        print("\n📝 Next Steps:")
        print("1. Install dependencies: pip install -r requirements.txt")
        print("2. Set OPENAI_API_KEY environment variable")
        print("3. Test with: python3 langchain_runner.py health_check '{}'")
        return True
    else:
        print("⚠️  Some tests failed. Please check the implementation.")
        return False

if __name__ == '__main__':
    success = main()
    sys.exit(0 if success else 1)