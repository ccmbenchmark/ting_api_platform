@mysqli
Feature: Ting bridge for api platform
  Background:
    Given I add "accept" header equal to "application/json"

  Scenario: Retrieve an empty collection
    When I send a GET request to "/api/mysqli_users"
    Then the JSON should be equal to:
        """
        []
        """

  Scenario Outline: Create a resource
    Given I add "Content-Type" header equal to "application/ld+json"
    When I send a POST request to "/api/mysqli_users" with body:
        """
        {
            "firstname": "<firstname>",
            "lastname": "<lastname>"
        }
        """

    Then the response status code should be 201
    And the JSON should be equal to:
        """
        {
            "firstname": "<firstname>",
            "lastname": "<lastname>"
        }
        """

    Examples:
      | lastname  | firstname  |
      | doe1 | john1 |
      | doe2 | john2 |
      | doe3 | john3 |
      | doe4 | john4 |

  Scenario: Retrieve a collection
    When I send a GET request to "/api/mysqli_users"
    Then the JSON should be equal to:
        """
        [
            {
                "firstname": "john1",
                "lastname": "doe1"
            },
            {
                "firstname": "john2",
                "lastname": "doe2"
            },
            {
                "firstname": "john3",
                "lastname": "doe3"
            },
            {
                "firstname": "john4",
                "lastname": "doe4"
            }
        ]
        """

  Scenario: Retrieve resources order by desc
    When I send a GET request to "/api/mysqli_users?order[lastname]=desc"
    Then the JSON should be equal to:
        """
        [
            {
                "firstname": "john4",
                "lastname": "doe4"
            },
            {
                "firstname": "john3",
                "lastname": "doe3"
            },
            {
                "firstname": "john2",
                "lastname": "doe2"
            },
            {
                "firstname": "john1",
                "lastname": "doe1"
            }
        ]
        """


  Scenario: Retrieve resources using pagination
    When I send a GET request to "/api/mysqli_users?order[firstname]=asc&myPage=2&myItemsPerPage=3"
    Then the JSON should be equal to:
        """
        [
            {
                "firstname": "john4",
                "lastname": "doe4"
            }
        ]
        """

  Scenario: Modify a resource
    Given I add "Content-Type" header equal to "application/ld+json"
    When I send a PUT request to "/api/mysqli_users/john1" with body:
        """
        {
            "firstname": "john1",
            "lastname": "new_value"
        }
        """
    Then the response status code should be 200
    And the JSON should be equal to:
        """
        {
            "firstname": "john1",
            "lastname": "new_value"
        }
        """

  Scenario: Retrieve a resource
    When I send a GET request to "/api/mysqli_users/john1"
    Then the JSON should be equal to:
        """
        {
            "firstname": "john1",
            "lastname": "new_value"
        }
        """

  Scenario: Retrieve resources with search filter
    When I send a GET request to "/api/mysqli_users?lastname=new_value"
    Then the JSON should be equal to:
        """
        [
            {
                "firstname": "john1",
                "lastname": "new_value"
            }
        ]
        """

  Scenario Outline: Delete a resource
    When I send a DELETE request to "/api/mysqli_users/<id>"
    Then the response status code should be 204

    Examples:
      | id    |
      | john1 |
      | john2 |
      | john3 |
      | john4 |

  Scenario:
    When I send a GET request to "/api/mysqli_users"
    Then the JSON should be equal to:
        """
        []
        """
