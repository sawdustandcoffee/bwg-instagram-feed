const sqlite3 = require('better-sqlite3');
const db = new sqlite3('/home/buckneri/projects/bwg-instagram-feed/features.db');
const row = db.prepare('SELECT id, name, description, steps, passes, in_progress FROM features WHERE id = 38').get();
console.log(JSON.stringify(row, null, 2));
