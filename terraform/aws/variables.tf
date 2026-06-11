variable "aws_region" {
  description = "Region de AWS donde se despliega la infraestructura"
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Entorno de despliegue: development, staging, production"
  type        = string
  default     = "production"
}

variable "instance_type" {
  description = "Tipo de instancia EC2 para la app Laravel"
  type        = string
  default     = "t3.micro"
}

variable "db_instance_class" {
  description = "Clase de instancia RDS PostgreSQL"
  type        = string
  default     = "db.t3.micro"
}

variable "db_password" {
  description = "Password del usuario administrador de PostgreSQL"
  type        = string
  sensitive   = true
}