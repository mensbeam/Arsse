The Arsse does not at this time have any first party clients. However, because The Arsse [supports existing protocols](/en/Supported_Protocols), most clients built for these protocols are compatible with The Arsse. Below are those that we personally know of and have tested with The Arsse, presented in alphabetical order.

<table class="clients">
 <thead>
  <tr>
   <th rowspan="2">Name</th>
   <th rowspan="2">OS</th>
   <th colspan="4">Protocol</th>
   <th rowspan="2">Notes</th>
  </tr>
  <tr>
   <th>Miniflux</th>
   <th>Nextcloud News</th>
   <th>Tiny Tiny RSS</th>
   <th>Fever</th>
  </tr>
 </thead>
 <tbody>
   <th colspan="7">Web</th>
  <tr>
  </tr>
  <tr>
   <td><a href="https://github.com/electh/nextflux">Nextflux</a></td>
   <td></td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/electh/ReactFlux">ReactFlux</a></td>
   <td>Web</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/reminiflux/reminiflux">reminiflux</a></td>
   <td></td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
    <p>Three-pane alternative front-end for Minflux. Does not include functionality for managing feeds. Requires token authentication.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/TheScientist/ttrss-pwa">Tiny Tiny RSS Progressive Web App</a></td>
   <td></td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Does not (<a href="https://github.com/TheScientist/ttrss-pwa/issues/7">yet</a>) support HTTP authentication. Does not include functionality for managing feeds.</p>
   </td>
  </tr>
 </tbody>
 <tbody>
  <tr>
   <th colspan="7">Desktop</th>
  </tr>
  <tr>
   <td><a href="https://hyliu.me/fluent-reader/">Fluent Reader</a></td>
   <td>Linux</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://lzone.de/liferea/">Liferea</a></td>
   <td>Linux</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Not compatible with HTTP authentication.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://newsboat.org/">Newsboat</a></td>
   <td>Linux, macOS</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Terminal-based client.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://gitlab.com/news-flash/news_flash_gtk">Newsflash</a></td>
   <td>Linux</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
    <p>Successor to FeedReader. One of the best on any platform</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/martinrotter/rssguard/">RSS Guard</a></td>
   <td>Windows, macOS, Linux</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Very basic client.</p>
   </td>
  </tr>
  </tr>
  <tr>
   <td><a href="https://bitbucket.org/thescientist/tiny-tiny-rss-wp8-client/src/master/">Tiny Tiny RSS Reader</td>
   <td>Windows</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Does not deal well with expired sessions; discontinued.</p>
   </td>
  </tr>
 </tbody>
 <tbody>
  <tr>
   <th colspan="7">Mobile</th>
  </tr>
  <tr>
   <td><a href="https://pbh.dev/cloudnews/">CloudNews</a></td>
   <td>iOS</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
    <p>Very bland looking application, but it functions well.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://play.google.com/store/apps/details?id=com.seazon.feedme">FeedMe</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td>
    <p>Not compatible with HTTP authentication.</p>
   </td>
  </tr>
  <tr>
   <td><a href="http://cocoacake.net/apps/fiery/">Fiery Feeds</a></td>
   <td>iOS</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="Y">✔</td>
   <td>
    <p>Rentalware - For the software to be usable (you can't even add feeds otherwise) a subscription fee must be paid.</p>
    <p>Supports HTTP authentication with Fever.</p>
    <p>Currently keeps showing items in the unread badge which have already been read.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://hyliu.me/fluent-reader-lite/">Fluent Reader Lite</a></td>
   <td>Android, iOS</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/fbarthelery/geekttrss">Geekttrss</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/DocMarty84/miniflutt">Miniflutt</a></td>
   <td>Android</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>Requires token authentication.</td>
  </tr>
  <tr>
   <td><a href="https://github.com/SimonSchubert/NewsOut">Newsout</a></td>
   <td>Android, iOS</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
    <p>iOS version only as source code; discontinued</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/nextcloud/news-android/">Nextcloud News</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
    <p>Official Android client for Nextcloud News.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/readrops/Readrops">Readrops</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td></td>
  </tr>
  <tr>
   <td><a href="http://tt-rss.org/">Tiny Tiny RSS</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Official Android client for Tiny Tiny RSS.</p>
   </td>
  </tr>
  <tr>
   <td><a href="http://github.com/nilsbraden/ttrss-reader-fork/">TTRSS-Reader</a></td>
   <td>Android</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
  <tr>
   <td><a href="https://www.goldenhillsoftware.com/unread/">Unread</a></td>
   <td>iOS</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
    <p>Trialware with one-time purchase.</p>
   </td>
  </tr>
 </tbody>
</table>

## Untested clients

<table class="clients">
 <thead>
  <tr>
   <th rowspan="2">Name</th>
   <th rowspan="2">OS</th>
   <th colspan="4">Protocol</th>
   <th rowspan="2">Notes</th>
  </tr>
  <tr>
   <th>Miniflux</th>
   <th>Nextcloud News</th>
   <th>Tiny Tiny RSS</th>
   <th>Fever</th>
  </tr>
 </thead>
 <tbody>
  <tr>
   <td><a href="https://github.com/jeena/feedthemonkey">FeedTheMonkey</a></td>
   <td>Linux</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/Huessenbergnetz/Fuoten">Fuoten</a></td>
   <td>Sailfish</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/mkiol/kaktus">Kaktus</a></td>
   <td>Sailfish, BlackBerry</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
  <tr>
   <td><a href="https://open-store.io/app/newsie.martinferretti">Newsie</a></td>
   <td>Ubuntu Touch</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/JuanJakobo/Pocketbook-Miniflux-Reader">Pocketbook Miniflux Reader</a></td>
   <td>Pocketbook</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td>
   </td>
  </tr>
  <tr>
   <td><a href="https://readkitapp.com/">ReadKit</a></td>
   <td>macOS, iOS</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
    <p>Requires purchase. Presumed to work.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/matoung/SparkReader">SparkReader</a></td>
   <td>Windows</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td>
    <p>Requires manual configuration.</p>
   </td>
  </tr>
  <tr>
   <td><a href="http://www.pluchon.com/en/tiny_reader_rss.php">tiny Reader RSS</a></td>
   <td>iOS</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p>Does not support HTTP authentication.</p>
   </td>
  </tr>
  <tr>
   <td><a href="https://github.com/cnlpete/ttrss">ttrss</a></td>
   <td>Sailfish</td>
   <td class="N">✘</td>
   <td class="N">✘</td>
   <td class="Y">✔</td>
   <td class="N">✘</td>
   <td>
    <p></p>
   </td>
  </tr>
 </tbody>
</table>
