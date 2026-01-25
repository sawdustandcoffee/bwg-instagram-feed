const Database = require('better-sqlite3');
const db = new Database('/home/buckneri/projects/bwg-instagram-feed/features.db');
const row = db.prepare('SELECT * FROM features WHERE id = 44').get();
console.log(JSON.stringify(row, null, 2));
