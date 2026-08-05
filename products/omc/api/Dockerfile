# OMC API — runtime
FROM python:3.12-slim

WORKDIR /srv/app

# Install package dependencies (dicom-core dari workspace di-build di docker
# terpisah; di sini cukup install deps runtime dasar).
COPY pyproject.toml ./
COPY app ./app

RUN pip install --no-cache-dir fastapi "uvicorn[standard]" pydantic

EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
