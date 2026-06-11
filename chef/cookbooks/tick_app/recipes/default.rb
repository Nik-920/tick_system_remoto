# ══════════════════════════════════════════════════════════════════
# Chef Recipe — tick_system_remoto (INCIDEX)
# Configura nginx como servidor web para la app Laravel.
# Equivalente al Dockerfile + nginx.conf del proyecto.
# ══════════════════════════════════════════════════════════════════

# ── Dependencias del sistema ───────────────────────────────────────
package 'nginx' do
  action :install
end

package 'docker.io' do
  action :install
end

package 'curl' do
  action :install
end

# ── Configuración de nginx ─────────────────────────────────────────
template '/etc/nginx/conf.d/tick_app.conf' do
  source 'tick_app.conf.erb'
  owner  'root'
  group  'root'
  mode   '0644'
  variables(
    server_name:   node['tick_app']['server_name'],
    app_port:      node['tick_app']['app_port'],
    document_root: node['tick_app']['document_root']
  )
  notifies :restart, 'service[nginx]', :delayed
end

# ── Servicio nginx ─────────────────────────────────────────────────
service 'nginx' do
  action [:enable, :start]
end

# ── Servicio Docker ────────────────────────────────────────────────
service 'docker' do
  action [:enable, :start]
end

# ── Directorio de la aplicacion ────────────────────────────────────
directory '/opt/tick' do
  owner  'www-data'
  group  'www-data'
  mode   '0755'
  action :create
end

# ── Levantar contenedores ──────────────────────────────────────────
execute 'docker-compose up -d' do
  cwd     '/opt/tick'
  command 'docker-compose up -d'
  action  :run
  not_if  'docker-compose ps | grep Up'
end

# ── Migraciones de base de datos ───────────────────────────────────
execute 'run migrations' do
  cwd     '/opt/tick'
  command 'docker-compose exec -T app php artisan migrate --force'
  action  :run
  only_if 'docker-compose ps | grep Up'
end