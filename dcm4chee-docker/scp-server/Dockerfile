FROM python:3.12-slim
RUN pip install pynetdicom
COPY run.sh /run.sh
RUN chmod +x /run.sh
ENTRYPOINT ["/run.sh"]
