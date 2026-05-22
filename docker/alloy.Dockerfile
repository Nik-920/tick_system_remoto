FROM grafana/alloy:latest
COPY alloy/config.alloy /etc/alloy/config.alloy
CMD ["run", "/etc/alloy/config.alloy"]
