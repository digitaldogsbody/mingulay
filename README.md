# Mingulay
![The western cliffs with the stack of Arnamuil in the centre and Bagh na h-Aoineig to the left.](https://upload.wikimedia.org/wikipedia/commons/6/68/Western_cliffs_of_Mingulay.jpg "Western cliffs of Mingulay")

*Tony Kinghorn / Western Cliffs of Mingulay / CC BY-SA 2.0*

### Overview
Mingulay is a PHP library for parsing file information out of [Zip files](https://en.wikipedia.org/wiki/ZIP_(file_format)).
It searches for the End of Central Directory Record, parses out the data, and then uses this to retrieve the Central Directory Records which contain the metadata of the files in the Zip.

### Usage
```php
$fp = fopen("example.zip", "rb");
$zip_info = new ZipRangeReader($fp);
var_dump($zip_info->files);
```

### Acknowledgements and thanks
* The [Webrecorder](https://github.com/webrecorder) team's [wabac.js](https://github.com/webrecorder/wabac.js). The initial inspiration for this library was an implementation of their [Zip Range Reader class](https://github.com/webrecorder/wabac.js/blob/main/src/wacz/ziprangereader.js) in PHP.
* [Jonatan Männchen](https://github.com/maennchen)'s [ZipStream-PHP library](https://github.com/maennchen/ZipStream-PHP).
* Diego, Allison, Albert, the rest of the METRO crew, and all the Archipelago Community for the discussions, ideas and support.

### What's in a name?
[Mingulay](https://en.wikipedia.org/wiki/Mingulay) is an island in the [Hebrides](https://en.wikipedia.org/wiki/Hebrides), an archipelago off the west coast of my home country of Scotland, and so it seemed a fitting pick as this library was developed as a contribution towards the fantastic [Archipelago project](https://archipelago.nyc/) ([GitHub](https://github.com/esmero)).

### License
[AGPL-3](https://www.gnu.org/licenses/agpl-3.0.txt)