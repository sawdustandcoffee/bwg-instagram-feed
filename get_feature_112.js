const sqlite3 = require('better-sqlite3');
const db = new sqlite3('features.db');
const feature = db.prepare('SELECT id, category, name, description, steps FROM features WHERE id = 112').get();
console.log(JSON.stringify(feature, null, 2));
