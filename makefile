build:
	docker build -f dockerfile -t feedparser-local .

run:
	docker compose up -d

down:
	docker compose down
