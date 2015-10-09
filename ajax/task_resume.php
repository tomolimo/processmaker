<?php

function HandleHeaderLine( $curl, $header_line ) {
    $temp = explode( ": ", $header_line ) ;
    if( is_array( $temp ) && $temp[0] == 'Set-Cookie' ) {
        header("Set-Cookie: ".$temp[1], false) ;
    }
    return strlen($header_line);
}


$ch = curl_init();
$pmURL = urldecode($_REQUEST['url']) ;
curl_setopt($ch, CURLOPT_URL, $pmURL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");
$body = curl_exec($ch); 
curl_close ($ch);


$pmBaseURL = explode( "/", $pmURL, 4 ) ;
array_pop( $pmBaseURL ) ;

echo "
    <!DOCTYPE html>

    <html lang='en' xmlns='http://www.w3.org/1999/xhtml'>
<head>
    <meta charset='utf-8' />
    <title></title>
    <link href='".implode("/", $pmBaseURL)."/css/classic-blank.css' rel='stylesheet' type='text/css'/>
</head>
<body aLink='#999999' leftMargin='0' rightMargin='0' topMargin='0' bgColor='#ffffff' text='#000000' vLink='#000000' link='#000000' marginwidth='0' marginheight='0'>
<table cellSpacing='0' cellPadding='0' width='100%' height='100%'>
	
	<tbody><tr>
		<td vAlign='top' width='100%'>
		
<table style='padding-top: 3px;' border='0' cellSpacing='0' cellPadding='0' width='100%'>
<tbody><tr>
<td align='center'>
<div style='margin: 0px;' id='publisherContent[0]' align='center'>  <form style='margin: 0px;' id='bHNTajBhT2lsNUhqMmFUTXg1cXM1NTdTWWR1ZDJB' class='formDefault' onsubmit='return validateForm(\"[]\");' encType='multipart/form-data' method='post' name='cases_Resume' action=''>  <div style='border-width: 1px; width: 550px; padding-right: 0px; padding-left: 0px;' class='borderForm'>
    <div class='boxTop'><div class='a'>&nbsp;</div><div class='b'>&nbsp;</div><div class='c'>&nbsp;</div></div>
    <div style='height: 100%;' class='content'>
    <table width='99%'>
      <tbody><tr>
        <td vAlign='top'>
          <table border='0' cellSpacing='0' cellPadding='0' width='100%'>
                                    <tbody><tr>
              <td class='FormTitle' colSpan='2' align=''><span >Task Properties</span></td>
            </tr>
                                           <tr>
              <td class='FormLabel' width='150'><label >Ongoing Task</label></td>
              <td class='FormFieldContent' width='400'>".urldecode($_REQUEST['taskname'])."</td>
                                                <tr>
              <td class='FormLabel' width='150'><label >By</label></td>
              <td class='FormFieldContent' width='400'>".urldecode($_REQUEST['username'])."</td>
            </tr>
                                  </tbody></table>
        </td>
      </tr>
    </tbody></table>
           </div>
       <div class='boxBottom'><div class='a'>&nbsp;</div><div class='b'>&nbsp;</div><div class='c'>&nbsp;</div></div>
       </div>
                                                                                                                                                                                                                                                                                         </form>



</div></td>
</tr>
</tbody></table>
		</td>
	</tr>
</tbody></table>


</body>
</html>
" ;

