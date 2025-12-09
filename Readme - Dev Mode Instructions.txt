How to start dev mode in the wordpress plugin project:

- Run produck-wordpress-project gulp clean; gulp default; gulp watch;
- Open wordpress on localhost:8081
- Aktivate Produck Plugin
- Go to Settings --> Permalink and set Permalink Structure "Beitragsname http://localhost:8081/beispielbeitrag/"

If you want to test new articles
- Run produck-con-frontend
- Add verified sites (localhost:8081) and set verified in postgresSQL DB
- Set External site localhost:8081 (currently whitelisted in chat-service, though domain is invalid)
- No you can publish articles for localhost:8081


