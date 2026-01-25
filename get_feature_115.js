const Database = require('better-sqlite3');
const db = new Database('features.db');

const feature = db.prepare('SELECT * FROM features WHERE id = ?').get(115);
if (feature) {
    console.log(JSON.stringify(feature, null, 2));
} else {
    console.log('Feature #115 not found');
}

db.close();
