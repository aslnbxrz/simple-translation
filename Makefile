.PHONY: test bump-patch bump-minor bump-major release help

bump-patch:
	./scripts/bump-version.sh patch

bump-minor:
	./scripts/bump-version.sh minor

bump-major:
	./scripts/bump-version.sh major

release: bump-patch

help:
	@echo "Available commands:"
	@echo "  make bump-patch  - Bump patch version (1.0.1 -> 1.0.2)"
	@echo "  make bump-minor  - Bump minor version (1.0.1 -> 1.1.0)"
	@echo "  make bump-major  - Bump major version (1.0.1 -> 2.0.0)"
	@echo "  make release     - Run tests and bump patch version"
	@echo "  make help        - Show this help message"
