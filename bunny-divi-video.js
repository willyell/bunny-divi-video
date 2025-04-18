(function() {
  tinymce.PluginManager.add('bunny_divi_video', function(editor) {
    // Store libraries once fetched
    let libraries = [];

    // Add a toolbar button (you can also hook into Divi's TinyMCE init)
    editor.ui.registry.addButton('bunnyVideo', {
      icon: 'embed',          // choose an icon
      tooltip: 'Insert Bunny Video',
      onAction: openDialog
    });

    //----------------------
    // 1. Open the dialog
    //----------------------
    function openDialog() {
      // First, fetch libraries (if not done already)
      fetchLibraries().then(() => {
        editor.windowManager.open({
          title: 'Insert Bunny Video',
          body: {
            type: 'panel',
            items: [
              {
                type: 'selectbox',
                name: 'library',
                id:   'bunny-library-select',
                label: 'Library',
                items: libraries.map(lib => ({
                  text:  lib.Name,
                  value: lib.Id,
                  // stash the per‑library key for later
                  apiKey: lib.ApiKey
                }))
              },
              {
                type: 'selectbox',
                name: 'video',
                id:   'bunny-video-select',
                label: 'Video',
                items: []
              }
            ]
          },
          buttons: [
            { type: 'cancel', text: 'Close' },
            { type: 'submit', text: 'Insert' }
          ],
          onSubmit: function(api) {
            const data = api.getData();
            // Insert a Divi‑friendly shortcode (or modify as needed)
            editor.insertContent(
              `[bunny_video library="${data.library}" video="${data.video}"]`
            );
            api.close();
          }
        });

        // wire up the change handler
        const win    = editor.windowManager.getWindows()[0];
        const libBox = win.find('#bunny-library-select')[0];

        libBox.on('change', () => {
          const libId = libBox.value();
          // grab the API key we stashed in items
          const apiKey = (libBox.settings.items.find(i => i.value === libId) || {}).apiKey;
          fetchVideos(libId, apiKey);
        });
      });
    }

    //----------------------
    // 2. Fetch Libraries
    //----------------------
    function fetchLibraries() {
      // if already loaded, skip
      if (libraries.length) {
        return Promise.resolve();
      }
      return fetch('https://api.bunny.net/videolibrary?page=1&perPage=1000', {
        headers: { 'AccessKey': bunnySettings.defaultAccessKey }
      })
      .then(res => res.json())
      .then(json => {
        libraries = json.Items || [];
      })
      .catch(err => {
        console.error('Error fetching libraries:', err);
        alert('Could not load Bunny libraries. Check console for details.');
      });
    }

    //----------------------
    // 3. Fetch Videos
    //----------------------
    function fetchVideos(libraryId, apiKey) {
      const url = `https://api.bunny.net/videolibrary/${libraryId}/videos?page=1&perPage=1000`;
      console.log('Fetching Bunny videos from', url);
      fetch(url, {
        headers: { 'AccessKey': apiKey }
      })
      .then(res => {
        console.log('→ status', res.status);
        return res.json();
      })
      .then(json => populateVideoList(json.Items || []))
      .catch(err => {
        console.error('Error fetching videos:', err);
        alert('Could not load videos. See console for details.');
      });
    }

    //----------------------
    // 4. Populate Video Listbox
    //----------------------
    function populateVideoList(videos) {
      const win      = editor.windowManager.getWindows()[0];
      const videoBox = win.find('#bunny-video-select')[0];
      const items    = videos.map(v => ({
        text:  v.Title || v.Name,
        value: v.Id
      }));

      videoBox.settings.items = items;
      win.layout();  // redraw the dialog so the list updates
      console.log('Populated', items.length, 'videos');
    }
  });
})();
