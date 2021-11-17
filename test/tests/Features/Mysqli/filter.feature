@mysqli
Feature: Ting filter for api platform
  Background:
    Given I add "accept" header equal to "application/json"

  Scenario Outline: Create a resource
    Given I add "Content-Type" header equal to "application/ld+json"
    When I send a POST request to "/api/mysqli_filters" with body:
        """
        {
            "name": "<name>",
            "value": "<value>",
            "valuePartial": "<value_partial>",
            "valueStart": "<value_start>",
            "valueEnd": "<value_end>",
            "valueWordStart": "<value_word_start>",
            "valueIpartial": "<value_ipartial>"
        }
        """

    Then the response status code should be 201
    And the JSON should be equal to:
        """
        {
            "name": "<name>",
            "value": "<value>",
            "valuePartial": "<value_partial>",
            "valueStart": "<value_start>",
            "valueEnd": "<value_end>",
            "valueWordStart": "<value_word_start>",
            "valueIpartial": "<value_ipartial>"
        }
        """

    Examples:
      | name  | value  | value_partial   | value_start | value_end       | value_word_start | value_ipartial  |
      | test1 | value1 | test            | test        | test_value      | test_value       | TEST_VALUE_TEST |
      | test2 | value2 | test            | value_test  | value_test      | test value_test  | test            |
      | test3 | value3 | test_value_test | test_value  | test_value_test | test test_value  | test            |
      | test4 | value4 | test            | test        | test            | value_test test  | test            |

  Scenario: Retrieve resources with search filter and strategy exact
    When I send a GET request to "/api/mysqli_filters?value=value1"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test1",
                "value": "value1",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test_value",
                "valueWordStart": "test_value",
                "valueIpartial": "TEST_VALUE_TEST"
            }
        ]
        """

  Scenario: Retrieve resources with search multiple filter
    When I send a GET request to "/api/mysqli_filters?value[]=value1&value[]=value2"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test1",
                "value": "value1",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test_value",
                "valueWordStart": "test_value",
                "valueIpartial": "TEST_VALUE_TEST"
            },
            {
                "name": "test2",
                "value": "value2",
                "valuePartial": "test",
                "valueStart": "value_test",
                "valueEnd": "value_test",
                "valueWordStart": "test value_test",
                "valueIpartial": "test"
            }
        ]
        """

  Scenario: Retrieve resources with search filter and strategy partial
    When I send a GET request to "/api/mysqli_filters?valuePartial=value"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test3",
                "value": "value3",
                "valuePartial": "test_value_test",
                "valueStart": "test_value",
                "valueEnd": "test_value_test",
                "valueWordStart": "test test_value",
                "valueIpartial": "test"
            }
        ]
        """

  Scenario: Retrieve resources with search filter and strategy start
    When I send a GET request to "/api/mysqli_filters?valueStart=value"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test2",
                "value": "value2",
                "valuePartial": "test",
                "valueStart": "value_test",
                "valueEnd": "value_test",
                "valueWordStart": "test value_test",
                "valueIpartial": "test"
            }
        ]
        """

  Scenario: Retrieve resources with search filter and strategy end
    When I send a GET request to "/api/mysqli_filters?valueEnd=value"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test1",
                "value": "value1",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test_value",
                "valueWordStart": "test_value",
                "valueIpartial": "TEST_VALUE_TEST"
            }
        ]
        """

  Scenario: Retrieve resources with search filter and strategy word start
    When I send a GET request to "/api/mysqli_filters?valueWordStart=value"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test2",
                "value": "value2",
                "valuePartial": "test",
                "valueStart": "value_test",
                "valueEnd": "value_test",
                "valueWordStart": "test value_test",
                "valueIpartial": "test"
            },
            {
                "name": "test4",
                "value": "value4",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test",
                "valueWordStart": "value_test test",
                "valueIpartial": "test"
            }
        ]
        """

  Scenario: Retrieve resources with search filter not declared
    When I send a GET request to "/api/mysqli_filters?name=test1"
    Then the JSON should be equal to:
        """
        [
            {
                "name": "test1",
                "value": "value1",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test_value",
                "valueWordStart": "test_value",
                "valueIpartial": "TEST_VALUE_TEST"
            },
            {
                "name": "test2",
                "value": "value2",
                "valuePartial": "test",
                "valueStart": "value_test",
                "valueEnd": "value_test",
                "valueWordStart": "test value_test",
                "valueIpartial": "test"
            },
            {
                "name": "test3",
                "value": "value3",
                "valuePartial": "test_value_test",
                "valueStart": "test_value",
                "valueEnd": "test_value_test",
                "valueWordStart": "test test_value",
                "valueIpartial": "test"
            },
            {
                "name": "test4",
                "value": "value4",
                "valuePartial": "test",
                "valueStart": "test",
                "valueEnd": "test",
                "valueWordStart": "value_test test",
                "valueIpartial": "test"
            }
        ]
        """

#  Scenario: Retrieve resources with search filter and strategy ipartial
#    When I send a GET request to "/api/mysqli_filters?valueIpartial=VaLuE"
#    Then the JSON should be equal to:
#        """
#        [
#            {
#                "name": "test1",
#                "value": "value1",
#                "valuePartial": "test",
#                "valueStart": "test",
#                "valueEnd": "test_value",
#                "valueWordStart": "test_value",
#                "valueIpartial": "TEST_VALUE_TEST"
#            }
#        ]
#        """
#
