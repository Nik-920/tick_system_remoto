# ══════════════════════════════════════════════════════════════════
# Diseño de migración a AWS — tick_system_remoto (INCIDEX)
# Este archivo representa la infraestructura equivalente a la que
# actualmente corre en Railway + Supabase, pero en AWS.
# ══════════════════════════════════════════════════════════════════

terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # Estado remoto — necesario para trabajo en equipo
  backend "s3" {
    bucket         = "tick-system-tfstate"
    key            = "production/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "tick-tfstate-locks"
  }
}

provider "aws" {
  region = var.aws_region
}

# ── Instancia EC2 — Laravel App ────────────────────────────────────
resource "aws_instance" "tick_app" {
  ami           = "ami-0c55b159cbfafe1f0"
  instance_type = var.instance_type

  vpc_security_group_ids = [aws_security_group.tick_sg.id]
  subnet_id              = aws_subnet.public.id

  user_data = <<-EOF
    #!/bin/bash
    yum update -y
    yum install -y docker
    service docker start
    usermod -a -G docker ec2-user
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    cd /opt/tick
    docker-compose up -d
  EOF

  tags = {
    Name        = "tick-system-app"
    Environment = var.environment
    Project     = "INCIDEX"
  }
}

# ── ElastiCache Redis — equivalente al redis del docker-compose ────
resource "aws_elasticache_cluster" "tick_redis" {
  cluster_id           = "tick-redis-${var.environment}"
  engine               = "redis"
  node_type            = "cache.t3.micro"
  num_cache_nodes      = 1
  parameter_group_name = "default.redis7"
  port                 = 6379
  subnet_group_name    = aws_elasticache_subnet_group.tick.name

  tags = {
    Environment = var.environment
    Project     = "INCIDEX"
  }
}

resource "aws_elasticache_subnet_group" "tick" {
  name       = "tick-redis-subnet-${var.environment}"
  subnet_ids = [aws_subnet.public.id]
}

# ── RDS PostgreSQL — equivalente a Supabase ────────────────────────
resource "aws_db_instance" "tick_postgres" {
  identifier        = "tick-postgres-${var.environment}"
  engine            = "postgres"
  engine_version    = "15"
  instance_class    = var.db_instance_class
  allocated_storage = 20
  storage_encrypted = true

  db_name  = "tick_${var.environment}"
  username = "tick_admin"
  password = var.db_password

  vpc_security_group_ids = [aws_security_group.tick_sg.id]
  db_subnet_group_name   = aws_db_subnet_group.tick.name

  skip_final_snapshot = var.environment != "production"
  deletion_protection = var.environment == "production"

  tags = {
    Environment = var.environment
    Project     = "INCIDEX"
  }
}

resource "aws_db_subnet_group" "tick" {
  name       = "tick-db-subnet-${var.environment}"
  subnet_ids = [aws_subnet.public.id]
}

# ── Security Group ─────────────────────────────────────────────────
resource "aws_security_group" "tick_sg" {
  name   = "tick-sg-${var.environment}"
  vpc_id = aws_vpc.tick_vpc.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Environment = var.environment
    Project     = "INCIDEX"
  }
}

# ── VPC y Subred ───────────────────────────────────────────────────
resource "aws_vpc" "tick_vpc" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_support   = true
  enable_dns_hostnames = true

  tags = {
    Name        = "tick-vpc-${var.environment}"
    Environment = var.environment
  }
}

resource "aws_subnet" "public" {
  vpc_id                  = aws_vpc.tick_vpc.id
  cidr_block              = "10.0.1.0/24"
  map_public_ip_on_launch = true

  tags = {
    Name        = "tick-public-subnet-${var.environment}"
    Environment = var.environment
  }
}

resource "aws_internet_gateway" "tick_igw" {
  vpc_id = aws_vpc.tick_vpc.id

  tags = {
    Name        = "tick-igw-${var.environment}"
    Environment = var.environment
  }
}

# ── Outputs ────────────────────────────────────────────────────────
output "app_public_ip" {
  description = "IP publica de la instancia Laravel"
  value       = aws_instance.tick_app.public_ip
}

output "redis_endpoint" {
  description = "Endpoint de ElastiCache Redis"
  value       = aws_elasticache_cluster.tick_redis.cache_nodes[0].address
}

output "db_endpoint" {
  description = "Endpoint de RDS PostgreSQL"
  value       = aws_db_instance.tick_postgres.endpoint
  sensitive   = true
}