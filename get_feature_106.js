const sqlite3 = require('better-sqlite3');
const db = new sqlite3('/home/buckneri/projects/bwg-instagram-feed/features.db');
const row = db.prepare('SELECT id, category, name, description, steps FROM features WHERE id=106').get();
console.log(JSON.stringify(row, null, 2));
