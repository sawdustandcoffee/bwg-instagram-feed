const Database = require('better-sqlite3');
const db = new Database('./features.db');

// Mark feature 6 as passing and clear in_progress
db.prepare('UPDATE features SET passes = 1, in_progress = 0 WHERE id = ?').run(6);
console.log('Feature #6 marked as passing');

// Verify
const updated = db.prepare('SELECT id, name, passes, in_progress FROM features WHERE id = 6').get();
console.log('Updated:', JSON.stringify(updated, null, 2));

// Get stats
const stats = db.prepare('SELECT COUNT(*) as total, SUM(passes) as passing FROM features').get();
console.log('Stats:', JSON.stringify(stats, null, 2));

db.close();
