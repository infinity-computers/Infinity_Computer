const fs = require('fs');
const path = require('path');

// Paths
const htmlPath = 'c:/xampp/htdocs/InfinityComputer/profile.html';
const imgPath = 'c:/xampp/htdocs/InfinityComputer/images/certificates/Asus Infinity_page-0001.jpg';

console.log('Reading image file...');
const imgBuffer = fs.readFileSync(imgPath);
const base64Str = imgBuffer.toString('base64');
console.log('Image base64 length:', base64Str.length);

console.log('Reading HTML file...');
let htmlContent = fs.readFileSync(htmlPath, 'utf8');
console.log('Original HTML size:', htmlContent.length);

// Locate the position to insert
const cert14Index = htmlContent.indexOf('<img id="cert14"');
if (cert14Index === -1) {
  console.error('Error: Could not find cert14 in HTML!');
  process.exit(1);
}

// Find the next `</div>` after `cert14Index`
const closeDivIndex = htmlContent.indexOf('</div>', cert14Index);
if (closeDivIndex === -1) {
  console.error('Error: Could not find closing div for cert14 wrapper!');
  process.exit(1);
}

// The end of `cert14` wrapper is at `closeDivIndex + 6` (length of `</div>`)
const insertPosition = closeDivIndex + 6;

// Construct the snippet
const indent = '\r\n        '; // matching the indentation of the file
const newCertSnippet = indent + '<div class="certificate-wrapper" oncontextmenu="return false;">' +
  indent + '  <div class="certificate-protector"></div>' +
  indent + '  <div class="certificate-watermark">INFINITY COMPUTER</div>' +
  indent + '  <img id="cert15" src="data:image/jpeg;base64,' + base64Str + '"' +
  indent + '    alt="Certificate">' +
  indent + '</div>';

// Insert and save
const newHtmlContent = htmlContent.substring(0, insertPosition) + newCertSnippet + htmlContent.substring(insertPosition);

console.log('Writing updated HTML file...');
fs.writeFileSync(htmlPath, newHtmlContent, 'utf8');
console.log('Successfully inserted new certificate (cert15) into profile.html!');
