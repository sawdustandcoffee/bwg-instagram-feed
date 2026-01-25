const Database = require('better-sqlite3');
const db = new Database('./features.db');
const feature = db.prepare('SELECT id, category, name, description, steps, passes, in_progress FROM features WHERE id = ?').get(122);
console.log(JSON.stringify(feature, null, 2));
db.close();
