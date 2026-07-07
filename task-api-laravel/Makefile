.PHONY: verify-all verify-api verify-db verify-dataset verify-benchmark help

SCRIPTS_DIR=scripts

help:
	@echo "Makefile targets:"
	@echo "  make verify-all        # run all verifications"
	@echo "  make verify-api        # compare API response structures"
	@echo "  make verify-db         # compare DB schema"
	@echo "  make verify-dataset    # check dataset counts"
	@echo "  make verify-benchmark  # validate benchmark headers"

verify-all: verify-api verify-db verify-dataset verify-benchmark

verify-api:
	@bash $(SCRIPTS_DIR)/check_api_responses.sh scripts/.env || true

verify-db:
	@bash $(SCRIPTS_DIR)/check_db_schema.sh scripts/.env || true

verify-dataset:
	@bash $(SCRIPTS_DIR)/check_dataset_counts.sh scripts/.env || true

verify-benchmark:
	@bash $(SCRIPTS_DIR)/check_benchmark_headers.sh scripts/.env || true
