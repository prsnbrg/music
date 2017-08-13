/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

$(document).ready(function () {

	var player = new PlayerWrapper();
	player.setVolume(50);
	var currentFile = null;
	var playing = false;
	var shareView = false;

	// UI elements (jQuery)
	var musicControls = null;
	var playButton = null;
	var pauseButton = null;
	var closeButton = null;

	function togglePlayback() {
		player.togglePlayback();
		playing = !playing;

		if (playing) {
			playButton.css('display', 'none');
			pauseButton.css('display', 'inline-block');
		} else {
			playButton.css('display', 'inline-block');
			pauseButton.css('display', 'none');
		}
	}

	function stop() {
		player.stop();
		musicControls.css('display', 'none');
		currentFile = null;
	}

	function createUi() {
		musicControls = $(document.createElement('div'))
			.attr('id', 'music-controls');

		playButton = $(document.createElement('img'))
			.attr('id', 'play')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'play-big'))
			.attr('alt', t('music', 'Play'))
			.css('display', 'inline-block')
			.click(togglePlayback);

		pauseButton = $(document.createElement('img'))
			.attr('id', 'pause')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'pause-big'))
			.attr('alt', t('music', 'Pause'))
			.css('display', 'none')
			.click(togglePlayback);

		closeButton = $(document.createElement('img'))
			.attr('id', 'close')
			.attr('class', 'control small svg')
			.attr('src', OC.imagePath('music', 'close'))
			.attr('alt', t('music', 'Close'))
			.click(stop);

		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(closeButton);

		var parentContainer = $('div#app-content');
		if (parentContainer.length == 0) {
			shareView = true;
			parentContainer = $('div#preview');
			musicControls.css('left', '0');
		}
		parentContainer.append(musicControls);

		// resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes
		var resizeControls = function() {
			musicControls.css('width', parentContainer.innerWidth() - getScrollBarWidth() + 'px');
		};
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', stop);
	}

	// Handle 'play' action on file row
	function onFilePlay(filename, context) {
		if (!musicControls) {
			createUi();
		}
		musicControls.css('display', 'inline-block');

		// Check if playing file changes
		var filerow = context.$file;
		if (currentFile != filerow.attr('data-id')) {
			currentFile = filerow.attr('data-id');

			player.stop();
			playing = false;
			var fileURL = context.fileList.getDownloadUrl(filename, context.dir);
			if (!shareView) {
				fileURL = fileURL + '?requesttoken=' + encodeURIComponent(OC.requestToken);
			}
			var fileMIME = filerow.attr('data-mime');
			player.fromURL(fileURL, fileMIME);
		}

		// Play/Pause
		togglePlayback();
	};

	// add play button here
	OCA.Files.fileActions.register(
			'audio',
			'music-play',
			OC.PERMISSION_READ,
			OC.imagePath('music', 'play-big'),
			onFilePlay,
			t('music', 'Play')
	);
	OCA.Files.fileActions.setDefault('audio', 'music-play');

	return true;
});
