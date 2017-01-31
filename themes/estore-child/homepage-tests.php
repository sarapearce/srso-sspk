<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>QUnit basic example</title>
  <link rel="stylesheet" href="https://code.jquery.com/qunit/qunit-2.1.1.css">
  <script
  src="https://code.jquery.com/jquery-3.1.1.min.js"
  integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
  crossorigin="anonymous"></script>
</head>
<body>
  <div id="qunit"></div>
  <div id="qunit-fixture"></div>
  <script src="https://code.jquery.com/qunit/qunit-2.1.1.js"></script>
  <script>
    QUnit.test( "a basic test example", function( assert ) {
      var value = "hello";
      assert.equal( value, "hello", "We expect value to be hello" );
    });

    QUnit.test( "facebook-social-link", function( assert ) {
      var fb = "https://www.facebook.com/SheSparkMag";
      assert.equal( fb, jQuery(".socicon-facebook").parent().attr('href'), "We expect the link to go to client facebook" );
    });
      QUnit.test( "twitter-social-link", function( assert ) {
      var tw = "http://twitter.com/shesparkchat";
      assert.equal( fb, jQuery(".socicon-twitter").parent().attr('href'), "We expect the link to go to client twitter" );
    });
    QUnit.test( "pinterest-social-link", function( assert ) {
      var pn = "http://pinterest.com/shesparkover40";
      assert.equal( fb, jQuery(".socicon-pinterest").parent().attr('href'), "We expect the link to go to client pinterest" );
});
      QUnit.test( "pinterest-social-link", function( assert ) {
      var ig = "http://instagram.com/shesparkmag";
      assert.equal( fb, jQuery(".socicon-instagram").parent().attr('href'), "We expect the link to go to client instagram" );
    });

  </script>
</body>
</html>
