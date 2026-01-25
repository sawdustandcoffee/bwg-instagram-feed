const db = require('better-sqlite3')('/home/buckneri/projects/bwg-instagram-feed/features.db');
const row = db.prepare('SELECT * FROM features WHERE id = 23').get();
console.log(JSON.stringify(row, null, 2));
