.PHONY: it
it: static-code-analysis ## Runs the coding-standards, dependency-analysis, static-code-analysis, and tests targets

.PHONY: static-code-analysis
static-code-analysis: vendor ## Runs a static code analysis with phpstan/phpstan
	mkdir -p .build/phpstan
	vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --memory-limit=-1

.PHONY: static-code-analysis-baseline
static-code-analysis-baseline: vendor ## Generates a baseline for static code analysis with phpstan/phpstan
	mkdir -p .build/phpstan
	vendor/bin/phpstan analyze --allow-empty-baseline --configuration=phpstan.neon.dist --generate-baseline=phpstan-baseline.neon --memory-limit=-1

vendor: composer.json composer.lock
	composer validate --strict
	composer install --no-interaction --no-progress
