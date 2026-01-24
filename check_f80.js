const Database = require('better-sqlite3');
const db = new Database('/home/buckneri/projects/bwg-instagram-feed/features.db');
const row = db.prepare('SELECT id, name, description, passes, in_progress FROM features WHERE id = 80').get();
console.log(JSON.stringify(row, null, 2));
db.close();
