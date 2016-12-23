var express = require('express');
var app = express()

app.get('/', function(req, res) {
  console.log('got a request');
  res.send('hello world');
});

app.listen(3000, function() {
  console.log('listening on port 3000');
});
