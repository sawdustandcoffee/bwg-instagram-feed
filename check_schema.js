const Database = require('better-sqlite3');
const db = new Database('./features.db');

// Get feature 10
const feature10 = db.prepare('SELECT * FROM features WHERE id = 10').get();
console.log('Feature #10:', JSON.stringify(feature10, null, 2));

// Get all features with in_progress status
const inProgress = db.prepare('SELECT id, name FROM features WHERE in_progress = 1').all();
console.log('\nFeatures in progress:', JSON.stringify(inProgress, null, 2));

db.close();
