<!-- HTML for static distribution bundle build -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>dimensy API</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('css/api-swagger/swagger-ui.css') }}" />
  <link rel="icon" type="image/png" href="image/icon.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="image/icon.png" sizes="16x16" />
  <style>
    html {
      box-sizing: border-box;
      overflow: -moz-scrollbars-vertical;
      overflow-y: scroll;
    }

    *,
    *:before,
    *:after {
      box-sizing: inherit;
    }

    body {
      margin: 0;
      background: #fafafa;
    }
  </style>
</head>

<body>
  <div id="swagger-ui"></div>

  <script src="{{ asset('js/api-swagger/swagger-ui-bundle.js') }}" charset="UTF-8"> </script>
  <script src="{{ asset('js/api-swagger/swagger-ui-standalone-preset.js') }}" charset="UTF-8"> </script>
  <script>
    window.onload = function() {
      // https://petstore.swagger.io/v2/swagger.json
      // Begin Swagger UI call region {{ asset('json/test-doc.json') }}
      const ui = SwaggerUIBundle({
        url: "{{ asset('json/dimensy.json') }}",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
      });
      // End Swagger UI call region

      window.ui = ui;
    };
  </script>
</body>

</html>