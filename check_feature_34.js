const Database = require('better-sqlite3');
const db = new Database('./features.db');

// Get feature 34
const feature34 = db.prepare('SELECT * FROM features WHERE id = 34').get();
console.log('Feature #34:', JSON.stringify(feature34, null, 2));

db.close();
