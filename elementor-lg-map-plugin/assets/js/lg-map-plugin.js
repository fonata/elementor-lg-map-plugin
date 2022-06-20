var map;

function initMapboxMap() {
  mapboxgl.accessToken = 'pk.eyJ1IjoiY2xpbWF0ZS1nb256byIsImEiOiJja3RvMjk1Y2MwOGt5Mm5sZzNoeHVnMm45In0.goMBluE8qz03EeDMl4PElA';
  map = new mapboxgl.Map({
  container: 'map',
    style: 'mapbox://styles/climate-gonzo/ckyecnidz4x6314nuzz75453s'
  });
  map.addControl(new mapboxgl.FullscreenControl());
}

function makeScrollable() {
  document.getElementById( 'zoomOverlay' ).style.display = 'none';
}

function toggleCheckboxPins(element) {
    if(element.checked){
        switch (element.id) {
          case 'blockade':
            jQuery(".blockadeP").each(function() {
                jQuery(this).removeClass("marker-display-none");
            });
            break;
          case 'soli':
            jQuery(".soliP").each(function() {
                jQuery(this).removeClass("marker-display-none");
            });
            break;
          case 'farbe':
            jQuery(".farbeP").each(function() {
                jQuery(this).removeClass("marker-display-none");
            });
            break;
          case 'gesa':
            jQuery(".gesaP").each(function() {
                jQuery(this).removeClass("marker-display-none");
            });
            break;
          case 'knast':
            jQuery(".knastP").each(function() {
                jQuery(this).removeClass("marker-display-none");
            }); 
            break;
        } 
    } else {
       switch (element.id) {
          case 'blockade':
           jQuery(".blockadeP").each(function() {
                jQuery(this).addClass("marker-display-none");
            });
            break;
          case 'soli':
            jQuery(".soliP").each(function() {
                jQuery(this).addClass("marker-display-none");
            });
            break;
          case 'farbe':
            jQuery(".farbeP").each(function() {
                jQuery(this).addClass("marker-display-none");
            });
            break;
          case 'gesa':
            jQuery(".gesaP").each(function() {
                jQuery(this).addClass("marker-display-none");
            }); 
            break;
          case 'knast':
            jQuery(".knastP").each(function() {
                jQuery(this).addClass("marker-display-none");
            });
            break;
        } 
    }
}