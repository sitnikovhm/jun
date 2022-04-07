 to Extend NGINX WebDAV
# Written by Jason LaPorte (jason@agoragames.com)
#
# Copyright (C) 2009 Agora Games, Inc.
#
# This software is provided 'as-is', without any express or implied warranty.
# In no event will the authors be held liable for any damages arising from the
# use of this software.
#
# Permission is granted to anyone to use this software for any purpose,
# including commercial applications, and to alter it and redistribute it
# freely, subject to the following restrictions:
#
# 1.  The origin of this software must not be misrepresented; you must not
#     claim that you wrote the original software. If you use this software
#     in a product, an acknowledgment in the product documentation would be
#     appreciated but is not required.
# 2.  Altered source versions must be plainly marked as such, and must not be
#     misrepresented as being the original software.
# 3.  This notice may not be removed or altered from any source distribution.
#
# MIME type determination, miscellaneous error handling, and general code
# structure adapted from YUNO's similar Perl CGI script[1]. More information
# about this script can be found on the Agora Games website[2].
#
# [1]: http://plan9.aichi-u.ac.jp/netlib/webappls/webdav.cgi
# [2]: http://blog.agoragames.com/2009/03/20/webdav-nginx-play-nice/
#
# Version History:
# *   3/17/2009: V1. (Initial version.)
# *   3/19/2009: V2. (Added basic support for the COPY and MOVE methods. Added
#     ZLib license for public distribution.)
# *   3/20/2009: V3. (Added recursive_copy and recursive_unlink functions,
#     eliminating the need to fork processes.)
#
# To-Do:
# *   Support (or, at least, stub out) PROPPATCH, LOCK, and UNLOCK operations.
# *   Properly support the DEPTH header in COPY requests.
# *   Properly support the various options for PROPFIND requests. (Ha!)

# Recursively delete $src. This is equivalent to UNIX's "rm -rf". Be very
# careful how you use it. Returns true on success and false on failure. (On
# failure, files that could not be deleted will obviously remain.)
function recursive_unlink($src)
{
    if (file_exists($src)) {
        if (is_dir($src)) {
            foreach (scandir($src) as $child)
                if ($child != '.' && $child != '..')
                    recursive_unlink("$src/$child");

            return rmdir($src);
        }

        else
            return unlink($src);
    }

    return false;
}

# Recursively copy $src to $dest. If $dest already exists, it will be
# overwritten. (This makes this function not quite semantically identical to
# UNIX's "cp -r".) Returns true on success and false on failure. On failure,
# $dest may be destroyed, regardless of whether it existed before the copy or
# not. Thus, if you call this function, treat $dest as forfeit.
# FIXME: We should preserve permissions in the copy.
function recursive_copy($src, $dest)
{
    if (file_exists($src)) {
        recursive_unlink($dest);

        if (is_dir($src)) {
            mkdir($dest);

            foreach (scandir($src) as $child)
                if ($child != '.' && $child != '..')
                    if (!recursive_copy("$src/$child", "$dest/$child")) {
                        recursive_unlink($dest);
                        return false;
                    }

            return true;
        }

        else if (is_link($src))
            return symlink(readlink($src), $dest);

        else
            return copy($src, $dest);
    }

    return false;
}

# Validate $key, treating it as $default if not supplied, according to the
# possible values in $options.
function validate($key, $default, $options)
{
    if (is_null($key) || $key === '') $key = $default;
    return $options[$key];
}

# Gets the MIME type of a particular file by examining its file extension. This
# could be greatly improved by doing something similar to the UNIX "file"
# command (e.g. examining headers), but this is a quick and easy hack.
function mime_type($path)
{
    # I love how PHP makes me want to kill myself. Why doesn't it support
    # nonscalar constants?
    $mime_types = array(
        'aif' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'asc' => 'text/plain',
        'atom' => 'text/plain',
        'au' => 'audio/basic',
        'avi' => 'video/x-msvideo',
        'bmp' => 'image/bmp',
        'c' => 'text/plain',
        'cc' => 'text/plain',
        'cgi' => 'text/plain',
        'cpp' => 'text/plain',
        'css' => 'text/css',
        'cxx' => 'text/plain',
        'doc' => 'application/msword',
        'dv' => 'video/x-dv',
        'eps' => 'application/postscript',
        'gif' => 'image/gif',
        'gz' => 'application/x-gzip',
        'h' => 'text/plain',
        'hpp' => 'text/plain',
        'hqx' => 'application/mac-binhex40',
        'htm' => 'text/html',
        'html' => 'text/html',
        'hxx' => 'text/plain',
        'jar' => 'application/java-archive',
        'jav' => 'text/plain',
        'java' => 'text/plain',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/plain',
        'lzh' => 'application/x-lzh',
        'm' => 'text/plain',
        'm4a' => 'audio/mp4a-latm',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mm' => 'text/plain',
        'mov' => 'video/quicktime',
        'mp2' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'ogg' => 'application/ogg',
        'pdf' => 'application/pdf',
        'php' => 'text/plain',
        'pict' => 'image/pict',
        'pl' => 'text/plain',
        'png' => 'image/png',
        'ppt' => 'application/vnd.ms-powerpoint',
        'ps' => 'application/postscript',
        'py' => 'text/plain',
        'rb' => 'text/plain',
        'rdf' => 'text/plain',
        'rm' => 'audio/x-pn-realaudio',
        'rtf' => 'text/rtf',
        'sh' => 'text/plain',
        'shtml' => 'text/html',
        'snd' => 'audio/basic',
        'svg' => 'image/svg+xml',
        'swf' => 'application/x-shockwave-flash',
        'tar' => 'application/x-tar',
        'tex' => 'application/x-tex',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'txt' => 'text/plain',
        'vrml' => 'model/vrml',
        'wav' => 'audio/x-wav',
        'wbmp' => 'image/vnd.wap.wbmp',
        'wrl' => 'model/vrml',
        'xbm' => 'image/x-xbitmap',
        'xhtml' => 'text/html',
        'xls' => 'application/vnd.ms-excel',
        'xml' => 'text/xml',
        'xpm' => 'image/x-xpixmap',
        'xsl' => 'text/xsl',
        'zip' => 'application/zip'
    );

    $extension = substr(strrchr($path, '.'), 1);
    $mime = @$mime_types[$extension];

    return $mime ? $mime : 'application/octet-stream';
}

# PROPFIND is recursive in nature, so it gets its own function. Since we
# assume "allprop" is set, it's not especially complicated: just stat() a file
# and format the results as XML. (Granted, the XML formatting is kinda silly,
# but you can blame the WebDAV folks for that.)
function propfind($root, $path, $depth, $hash_depth)
{
    $log = new Logging();
    // set path and name of log file (optional)
    $log->lfile('/opt/nginx/php/mylog.log');
        $log->lwrite($path);

    $href = str_replace($root, "", $path);
    $hrefParts = preg_split("/\/[a-z0-9]*?\-\-/", $href);
    $href = '';
    foreach ($hrefParts as $part) {
        $href = $href . $part;
    }
    $href = str_replace(array('%2F', '+'), array('/', '%20'), urlencode($href));

    $file = $path;
    $exists = file_exists($file);
    $dir = NULL;
    $stat = NULL;

    if ($href === '')
        $href = '/';

        if (!$exists) {
                $file = preg_replace("/\/((...)--)\/((.)..--)\/(.*?--\/){2}/i", "/\$2\$4--/", $file);
                $log->lwrite("Rewritten: $file");
                $exists = file_exists($file);
        }

    if ($exists) {
        $dir = is_dir($file);
        $stat = stat($file);
    }

    // write message to the log file
    echo ('<response>');
    echo ("<href>$href</href>");
    echo ('<propstat>');

    # File not found.
    if (!$exists)
        echo ('<status>HTTP/1.1 404 File Not Found</status>');

        # If we can't stat the file, it's probably a permissions issue. (I use a
        # 403 and not a 401 because the client can never recover from the error--
        # it's based on the server's permissions, not the client's.)
    else if (!$stat)
        echo ('<status>HTTP/1.1 403 Forbidden</status>');

    else {
        echo ('<status>HTTP/1.1 200 OK</status>');
        echo ('<prop>');

        $name = htmlspecialchars(basename($file));
        $created = gmdate('c', $stat['ctime']);
        $modified = gmdate('c', $stat['mtime']);

        # Display various general properties.
        echo ("<displayname>$name</displayname>");
        echo ("<creationdate>$created</creationdate>");
        echo ("<getlastmodified>$modified</getlastmodified>");
        echo ('<supportedlock/>');

        # If it's a directory, say so.
        if ($dir)
            echo ('<resourcetype><collection/></resourcetype>');

            # Otherwise, print out statistics that only make sense on files.
        else {
            $size = $stat['size'];
            $mime = mime_type($file);
            $etag = "{$stat['dev']}-{$stat['ino']}-{$stat['mtime']}";

            echo ('<resourcetype/>');
            echo ("<getcontentlength>$size</getcontentlength>");
            echo ("<getcontenttype>$mime</getcontenttype>");
            echo ("<getetag>$etag</getetag>");
        }

        echo ('</prop>');
    }

    echo ('</propstat>');
    echo ('</response>');
    # If this is a directory and we're set to recurse, then also print out
    # PROPFIND responses for all of this directory's children.
    if ($depth > 0) {
        $pattern = '';
        for ($i = 0; $i < $hash_depth; $i++) {
            $pattern = $pattern."/*--";
        }
        foreach (glob($file.$pattern."/*") as $child) {
            propfind($root, $child, $depth - 1, $hash_depth);
        }
    }
}

# Response handling begins here. (Determine what method is being called, and
# respond appropriately.)

date_default_timezone_set('UTC');

$request_method = getenv('REQUEST_METHOD');

switch ($request_method) {
    # PROPFIND supports a truly staggering amount of options and flags to limit
    # or define the various pieces of data you're interested in retrieving. We
    # pretend that the client has always specified "allprop" (that is, complete
    # information about everything), and make it the client's responsibility to
    # pull out less information if so desired.
    case 'PROPFIND':
        # Figure out what file they're looking at.
        $document_root = getenv('DOCUMENT_ROOT');
        $document_uri = urldecode(rtrim(getenv('DOCUMENT_URI'), '/'));

        # Figure out what depth to recurse to. Valid values are 0, 1, and infinity.
        $depth = validate(getenv('DEPTH'),
                          'infinity',
                          array('0' => 0, '1' => 1, 'infinity' => 'infinity'));

        # Choke if they specify an invalid depth.
        if (is_null($depth))
            header('HTTP/1.1 400 Bad Request');

            # "allprop" with an infinite depth is a scary proposition. Supporting it is
            # both optional and stupid, so I don't.
        else if ($depth === 'infinity') {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/xml');

            echo ('<error xmlns="DAV:">');
            echo ('<propfind-finite-depth/>');
            echo ('</error>');
        }

            # Otherwise, give them the requested information.
        else {

            header("HTTP/1.1 207 Multi-Status");
            header('Content-Type: text/xml');

            echo ('<multistatus xmlns="DAV:">');
            propfind($document_root, $document_root . $document_uri, $depth, getenv('HASH_DEPTH'));
            echo ('</multistatus>');
        }

        break;

    # We handle COPY and MOVE together, since they're almost identical. COPY
    # should support the Depth header, which modifies the semantics of the copy.
    # We ignore it and assume an infinite depth. Additionally, we do not support
    # copies between servers--all copies must be local. Finally, we don't
    # properly check for disk space errors, but a generic 500 should be good
    # enough.
    case 'COPY':
    case 'MOVE':
        # Figure out what files they're looking at.
        $host = getenv('HOST');
        $document_root = getenv('DOCUMENT_ROOT');
        $document_uri = urldecode(rtrim(getenv('DOCUMENT_URI'), '/'));

        $destination = NULL;
        $destination_host = NULL;
        $url = parse_url(getenv('DESTINATION'));

        if ($url) {
            $destination = urldecode(rtrim($url['path'], '/'));
            $destination_host = $url['host'];
        }

        $source_exists = file_exists($document_root . $document_uri);
        $destination_exists = file_exists($document_root . $destination);

        # Do we overwrite the destination file?
        $overwrite = validate(getenv('OVERWRITE'),
                              'T',
                              array('T' => true, 't' => true,
                                   'F' => false, 'f' => false));

        # Choke if they specify an invalid destination or depth.
        if (is_null($destination))
            header('HTTP/1.1 400 Bad Request');

            # Disallow copying/moving to a remote host.
        else if ($host != $destination_host)
            header('HTTP/1.1 502 Bad Gateway');

            # Fail if the source doesn't exist.
        else if (!$source_exists)
            header('HTTP/1.1 404 File Not Found');

            # Disallow copying/moving a file to itself.
        else if ($document_uri == $destination)
            header('HTTP/1.1 403 Forbidden');

            # Fail if the destination file exists and they said they didn't want to
            # overwrite it.
        else if ($overwrite == false && $destination_exists)
            header('HTTP/1.1 412 Precondition Failed');

            # If we're doing a copy, copy the files.
            # FIXME: We resort to shell since PHP doesn't support a recursive copy and
            # I didn't want to bother implementing it (though it would be a good idea
            # to do so at some point).
        else if ($request_method == 'COPY' &&
                 !recursive_copy($document_root . $document_uri,
                                 $document_root . $destination)
        )
            header('500 Internal Server Error');

            # If we're doing a move, move the files.
        else if ($request_method == 'MOVE' &&
                 !rename($document_root . $document_uri,
                         $document_root . $destination)
        )
            header('500 Internal Server Error');

        else
            header($destination_exists ?
                           'HTTP/1.1 204 No Content' :
                           'HTTP/1.1 201 Created');

        break;

    # In case they ask, pretend that we actually know what we're talking about.
    # (The only methods conspicuously absent are PROPPATCH, LOCK, and UNLOCK. We
    # tell the clients that we don't support them.)
    case 'OPTIONS':
        header('HTTP/1.1 200 OK');
        header('Allow: OPTIONS, GET, HEAD, POST, PUT, DELETE, PROPFIND');
        header('DAV: 1, 2');
        break;

    # The following methods are valid but unimplemented. In theory, NGINX is
    # supposed to handle each of them anyway.
    case 'GET':
    case 'HEAD':
    case 'POST':
    case 'PUT':
    case 'DELETE':
    case 'MKCOL':
        header('HTTP/1.1 501 Not Implemented');
        break;

    # Any other methods are unknown.
    default:
        header('HTTP/1.1 400 Bad Request');
        break;
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    $start = $length * -1; //negative
    return (substr($haystack, $start) === $needle);
}

function lastIndexOf($string, $item)
{
    $index = strpos(strrev($string), strrev($item));
    if ($index) {
        $index = strlen($string) - strlen($item) - $index;
        return $index;
    } else
        return -1;
}

function cutWebDavFolders($path)
{
    if ($path == '/') {
        return $path;
    }
    $folders = preg_split('/\//', $path);
    $result = NULL;
    foreach ($folders as $folder) {
        if ($folder != '' && !endsWith($folder, '--')) {
            $result = "$result/$folder";
        }
    }
    return $result;
}

/**
 * Logging class:
 * - contains lfile, lopen and lwrite methods
 * - lfile sets path and name of log file
 * - lwrite will write message to the log file
 * - first call of the lwrite will open log file implicitly
 * - message is written with the following format: hh:mm:ss (script name) message
 */
class Logging
{
    // define default log file
    private $log_file = '/tmp/logfile.log';
    // define file pointer
    private $fp = null;

    // set log file (path and name)
    public function lfile($path)
    {
        $this->log_file = $path;
    }

    // write message to the log file
    public function lwrite($message)
    {
        // if file pointer doesn't exist, then open log file
        if (!$this->fp) $this->lopen();
        // define script name
        $script_name = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
        // define current time
        $time = date('H:i:s');
        // write current time, script name and message to the log file
        fwrite($this->fp, "$time ($script_name) $message\n");
    }

    // open log file
    private function lopen()
    {
        // define log file path and name
        $lfile = $this->log_file;
        // define the current date (it will be appended to the log file name)
        $today = date('Y-m-d');
        // open log file for writing only; place the file pointer at the end of the file
        // if the file does not exist, attempt to create it
        $this->fp = fopen($lfile . '_' . $today, 'a') or exit("Can't open $lfile!");
    }
}
?>

