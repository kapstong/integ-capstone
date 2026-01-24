Forecasting integration

Files added:
- `scripts/budget_forecast.py`: CLI script to run forecasting. Accepts `<input_csv> <predict_months>` and outputs JSON.
- `requirements.txt`: Python dependencies (`pandas`, `numpy`, `statsmodels`).

Setup
1. Install Python (3.8+ recommended). Either create a virtualenv at `.venv` in project root or set environment variable `PYTHON_EXECUTABLE` to the python executable path used by the webserver.

Example (Windows PowerShell):

```powershell
python -m venv .venv
.\.venv\Scripts\pip install -r requirements.txt
$env:PYTHON_EXECUTABLE = (Resolve-Path .\.venv\Scripts\python.exe).Path
```

Usage
- The web UI calls `api/budgets.php?action=forecast&months=36&predict_months=12`.
- The API aggregates historical monthly expense data and runs the Python script; the returned JSON contains `method`, `history`, `forecast`, and `details`.

If Python or dependencies are unavailable, the script falls back to a simple average-growth forecast and still returns a readable JSON result.
