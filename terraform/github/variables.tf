variable "github_token" {
  description = "Personal Access Token de GitHub con permisos repo y workflow"
  type        = string
  sensitive   = true
}

variable "github_owner" {
  description = "Usuario u organizacion duena del repositorio"
  type        = string
  default     = "Nik-920"
}

variable "repository_name" {
  description = "Nombre del repositorio"
  type        = string
  default     = "tick_system_remoto"
}