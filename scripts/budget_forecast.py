#!/usr/bin/env python3
import sys
import json
import os
from datetime import datetime

try:
    import pandas as pd
    import numpy as np
    from statsmodels.tsa.holtwinters import ExponentialSmoothing
except Exception as e:
    pd = None
    np = None
    ExponentialSmoothing = None


def simple_forecast(dates, values, periods):
    # fallback: average monthly growth
    if len(values) < 2:
        last = float(values[-1]) if values else 0.0
        return {'method': 'naive', 'forecast': [{'date': (datetime.strptime(dates[-1], '%Y-%m-%d').replace(day=1).strftime('%Y-%m-01')),'value': last} for _ in range(periods)], 'details': 'Not enough history; returning last value repeated.'}
    # compute monthly growth rates
    vals = [float(v) for v in values]
    growths = []
    for i in range(1, len(vals)):
        prev = vals[i-1]
        if prev == 0:
            growths.append(0.0)
        else:
            growths.append((vals[i] - prev) / prev)
    avg_growth = sum(growths) / len(growths) if growths else 0.0
    last_date = datetime.strptime(dates[-1], '%Y-%m-%d')
    last_val = vals[-1]
    forecast = []
    for i in range(1, periods+1):
        # advance month
        m = (last_date.month - 1 + i) % 12 + 1
        y = last_date.year + ((last_date.month - 1 + i) // 12)
        dstr = f"{y:04d}-{m:02d}-01"
        last_val = last_val * (1 + avg_growth)
        forecast.append({'date': dstr, 'value': round(float(last_val), 2)})
    return {'method': 'avg_growth', 'forecast': forecast, 'details': f'Average monthly growth rate: {avg_growth:.4f}'}


def main():
    if len(sys.argv) < 3:
        print(json.dumps({'error': 'Usage: budget_forecast.py <input_csv> <predict_months>'}))
        sys.exit(1)

    input_csv = sys.argv[1]
    try:
        periods = int(sys.argv[2])
    except:
        periods = 12

    if not os.path.isfile(input_csv):
        print(json.dumps({'error': 'Input file not found'}))
        sys.exit(1)

    if pd is None or ExponentialSmoothing is None:
        # use simple fallback
        try:
            with open(input_csv, 'r') as f:
                lines = f.read().strip().splitlines()[1:]
                dates = []
                vals = []
                for ln in lines:
                    parts = ln.split(',')
                    if len(parts) >= 2:
                        dates.append(parts[0])
                        vals.append(float(parts[1]))
            res = simple_forecast(dates, vals, periods)
            print(json.dumps(res))
            sys.exit(0)
        except Exception as e:
            print(json.dumps({'error': 'Fallback forecast failed', 'exc': str(e)}))
            sys.exit(2)

    try:
        df = pd.read_csv(input_csv, parse_dates=['date'])
        df = df.sort_values('date')
        df = df.set_index('date')
        # ensure monthly frequency
        df = df.asfreq('MS')
        series = df['amount'].fillna(0)

        method = 'holt_winters'
        details = {}

        # Choose seasonal if enough history
        seasonal = 'add' if len(series) >= 24 else None
        try:
            model = ExponentialSmoothing(series, trend='add', seasonal=seasonal, seasonal_periods=12 if seasonal else None)
            fit = model.fit(optimized=True)
            pred = fit.forecast(periods)
            history = [{'date': d.strftime('%Y-%m-01'), 'value': float(series.loc[d])} for d in series.index]
            forecast = [{'date': d.strftime('%Y-%m-01'), 'value': float(pred.loc[d])} for d in pred.index]
            details['aic'] = getattr(fit, 'aic', None)
            details['params'] = {k: float(v) for k, v in getattr(fit, 'params', {}).items()} if hasattr(fit, 'params') else {}
            out = {
                'method': method,
                'history': history,
                'forecast': forecast,
                'details': details
            }
            print(json.dumps(out))
            sys.exit(0)
        except Exception as e:
            # fallback to simple growth
            dates = [d.strftime('%Y-%m-%d') for d in series.index]
            vals = [float(v) for v in series.values]
            res = simple_forecast(dates, vals, periods)
            res['details'] = 'Holt-Winters failed: ' + str(e)
            print(json.dumps(res))
            sys.exit(0)
    except Exception as e:
        print(json.dumps({'error': 'Forecast processing failed', 'exc': str(e)}))
        sys.exit(1)

if __name__ == '__main__':
    main()
