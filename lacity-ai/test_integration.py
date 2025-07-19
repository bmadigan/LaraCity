#!/usr/bin/env python3
"""
Integration test for PHP-Python bridge
Tests the complete system without requiring external API keys
"""

import json
import sys
import subprocess
from pathlib import Path

def test_php_python_bridge():
    """Test the PHP-Python bridge interface"""
    print("🔗 Testing PHP-Python Bridge Integration")
    print("=" * 50)
    
    # Test data that PHP would send
    test_cases = [
        {
            "name": "Complaint Analysis",
            "operation": "analyze_complaint", 
            "data": {
                "complaint_data": {
                    "id": 123,
                    "type": "Noise",
                    "description": "Loud construction noise at residential building",
                    "borough": "MANHATTAN",
                    "agency": "DEP",
                    "submitted_at": "2025-07-19 10:30:00"
                }
            },
            "expected_fields": ["analysis", "complaint_id"]
        },
        {
            "name": "Health Check",
            "operation": "health_check",
            "data": {},
            "expected_fields": ["status", "components", "config"]
        },
        {
            "name": "System Statistics",
            "operation": "get_stats",
            "data": {},
            "expected_fields": ["system", "components"]
        }
    ]
    
    results = []
    
    for test_case in test_cases:
        print(f"\n📋 Testing: {test_case['name']}")
        print("-" * 30)
        
        try:
            # Simulate PHP calling Python
            command = [
                sys.executable,
                "langchain_runner.py",
                test_case["operation"], 
                json.dumps(test_case["data"])
            ]
            
            # Set environment to avoid API calls
            env = {"OPENAI_API_KEY": "test-key-for-validation"}
            
            result = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=10,
                env=env,
                cwd=Path(__file__).parent
            )
            
            print(f"Command: {' '.join(command)}")
            print(f"Exit code: {result.returncode}")
            
            if result.stdout:
                try:
                    response = json.loads(result.stdout)
                    print(f"✅ Valid JSON response received")
                    print(f"Success: {response.get('success', 'unknown')}")
                    
                    if response.get('success'):
                        data = response.get('data', {})
                        missing_fields = [
                            field for field in test_case['expected_fields']
                            if field not in data
                        ]
                        
                        if not missing_fields:
                            print(f"✅ All expected fields present: {test_case['expected_fields']}")
                            results.append(("PASS", test_case['name'], "All tests passed"))
                        else:
                            print(f"⚠️  Missing fields: {missing_fields}")
                            results.append(("PARTIAL", test_case['name'], f"Missing: {missing_fields}"))
                    else:
                        error = response.get('error', 'Unknown error')
                        print(f"❌ Operation failed: {error}")
                        results.append(("FAIL", test_case['name'], error))
                        
                except json.JSONDecodeError as e:
                    print(f"❌ Invalid JSON response: {e}")
                    print(f"Stdout: {result.stdout[:200]}...")
                    results.append(("FAIL", test_case['name'], "Invalid JSON"))
            
            if result.stderr:
                print(f"Stderr: {result.stderr[:200]}...")
                
        except subprocess.TimeoutExpired:
            print(f"❌ Test timed out")
            results.append(("FAIL", test_case['name'], "Timeout"))
        except Exception as e:
            print(f"❌ Test error: {str(e)}")
            results.append(("FAIL", test_case['name'], str(e)))
    
    # Summary
    print("\n" + "=" * 50)
    print("📊 Integration Test Results")
    print("=" * 50)
    
    passed = sum(1 for status, _, _ in results if status == "PASS")
    partial = sum(1 for status, _, _ in results if status == "PARTIAL")
    failed = sum(1 for status, _, _ in results if status == "FAIL")
    
    for status, name, details in results:
        if status == "PASS":
            print(f"✅ {name}: PASSED")
        elif status == "PARTIAL":
            print(f"⚠️  {name}: PARTIAL - {details}")
        else:
            print(f"❌ {name}: FAILED - {details}")
    
    total = len(results)
    print(f"\nSummary: {passed} passed, {partial} partial, {failed} failed out of {total} tests")
    
    if passed + partial == total:
        print("\n🎉 Integration tests completed successfully!")
        print("\n📝 Next Steps for Full Deployment:")
        print("1. Install Python dependencies: pip install -r requirements.txt")
        print("2. Set OPENAI_API_KEY in Laravel .env file")
        print("3. Update PythonAiBridge service to use langchain_runner.py")
        print("4. Test with real complaints: php artisan db:seed --class=ComplaintSeeder")
        return True
    else:
        print(f"\n⚠️  Some integration tests failed. System structure needs review.")
        return False

def main():
    """Run integration tests"""
    try:
        return test_php_python_bridge()
    except KeyboardInterrupt:
        print("\n\n⚠️  Tests interrupted by user")
        return False
    except Exception as e:
        print(f"\n\n❌ Test suite failed: {str(e)}")
        return False

if __name__ == '__main__':
    success = main()
    sys.exit(0 if success else 1)