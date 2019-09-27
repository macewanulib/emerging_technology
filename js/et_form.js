jQuery(document).ajaxSuccess(function (e) {
  if(jQuery('#request-success').length > 0) {
    window.scrollTo(0,0);
  }
});
