const Database = require('better-sqlite3');
const db = new Database('features.db');
const pending = db.prepare('SELECT COUNT(*) as count FROM features WHERE passes = 0 AND in_progress = 0').get();
console.log('Pending features remaining:', pending.count);
if (pending.count === 0) {
    console.log('');
    console.log('='.repeat(60));
    console.log('BATCH COMPLETE! PUSH AND RELEASE REQUIRED!');
    console.log('='.repeat(60));
}
db.close();
