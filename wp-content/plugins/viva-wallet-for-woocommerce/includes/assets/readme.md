// developers note.
// if you want to change JS of CSS of this plugin (even though this is not recommended as it will be replaced when the plugin is updated)
// you need to run minification of files manually

1. install npm globally
   
2. install uglifyjs by running the below command in a terminal window (more info here https://www.npmjs.com/package/uglify-js)
   npm install uglify-js -g
   
3. install uglifycss by running the below command in a terminal window (more info here https://www.npmjs.com/package/uglifycss)
   npm install uglifycss -g
   
4. open main directory of viva wallet plugin in a terminal window ( cd .. /wp-content/plugins/viva-wallet-for-woocommerce )

5. run the below lines to minify css files

uglifycss includes/assets/css/vivawallet-styles-apple-pay.css --output includes/assets/css/vivawallet-styles-apple-pay.min.css
uglifycss includes/assets/css/vivawallet-styles-cc-logos.css --output includes/assets/css/vivawallet-styles-cc-logos.min.css
uglifycss includes/assets/css/vivawallet-styles-core.css --output includes/assets/css/vivawallet-styles-core.min.css

6. run the below lines to minify js files

uglifyjs includes/assets/js/admin-vivawallet.js  -c -m -o includes/assets/js/admin-vivawallet.min.js
uglifyjs includes/assets/js/apple-pay-vivawallet.js -c -m -o includes/assets/js/apple-pay-vivawallet.min.js
uglifyjs includes/assets/js/payment-vivawallet.js -c -m -o includes/assets/js/payment-vivawallet.min.js