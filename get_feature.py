import sqlite3
import json

conn = sqlite3.connect('features.db')
c = conn.cursor()
c.execute('SELECT id, name, description, steps, passes, in_progress FROM features WHERE id = 33')
row = c.fetchone()
if row:
    print(f'ID: {row[0]}')
    print(f'Name: {row[1]}')
    print(f'Description: {row[2]}')
    print(f'Steps: {row[3]}')
    print(f'Status: {row[4]}')

# Also get stats
c.execute('SELECT COUNT(*) as total, SUM(CASE WHEN status = "passes" THEN 1 ELSE 0 END) as passing FROM features')
stats = c.fetchone()
print(f'\n--- Stats ---')
print(f'Total: {stats[0]}, Passing: {stats[1]}')

conn.close()
