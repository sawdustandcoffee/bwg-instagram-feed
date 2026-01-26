#!/usr/bin/env python3
import sqlite3
import json

conn = sqlite3.connect('/home/buckneri/projects/bwg-instagram-feed/features.db')
cursor = conn.cursor()

cursor.execute('SELECT id, name, description, steps, category, passes, in_progress FROM features WHERE id = 44')
row = cursor.fetchone()

if row:
    print(f"Feature #44:")
    print(f"ID: {row[0]}")
    print(f"Name: {row[1]}")
    print(f"Category: {row[4]}")
    print(f"Passes: {row[5]}")
    print(f"In Progress: {row[6]}")
    print(f"\nDescription:")
    print(row[2])
    print(f"\nSteps:")
    steps = json.loads(row[3])
    for i, step in enumerate(steps, 1):
        print(f"  {i}. {step}")
else:
    print("Feature #44 not found")

conn.close()
