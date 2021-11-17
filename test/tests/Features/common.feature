@mysqli @postgresql
Feature: Ting bridge for api platform
  Background:
    Given I add "accept" header equal to "application/json"

  Scenario: Index
    When I send a GET request to "/api"
    Then the JSON should be equal to:
        """
        {
            "resourceNameCollection": [
                "App\\Mysqli\\Entity\\MysqliUser",
                "App\\Mysqli\\Entity\\MysqliFilter",
                "App\\Pgsql\\Entity\\PgsqlUser",
                "App\\Pgsql\\Entity\\PgsqlFilter"
            ]
        }
        """
