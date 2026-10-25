# Build Stage
FROM rust:1.80-alpine AS builder

WORKDIR /app
RUN apk add --no-cache musl-dev

COPY Cargo.toml ./
COPY src ./src

RUN cargo build --release

# Final Scratch/Alpine Production Stage
FROM alpine:3.20

WORKDIR /app
RUN apk add --no-cache ca-certificates

COPY --from=builder /app/target/release/raccon-search-engine /app/raccon-search-engine

EXPOSE 8088
ENV BIND_ADDR=0.0.0.0:8088

ENTRYPOINT ["/app/raccon-search-engine"]
