const Database = require('better-sqlite3');
const db = new Database('/home/buckneri/projects/bwg-instagram-feed/features.db');
const feature = db.prepare('SELECT id, category, name, description, steps FROM features WHERE id = 108').get();
console.log(JSON.stringify(feature, null, 2));
db.close();
