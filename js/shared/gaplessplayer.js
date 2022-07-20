/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022
 */

OCA.Music = OCA.Music || {};

OCA.Music.GaplessPlayer = function() {
	var m_self = this;
	var m_currentPlayer = new OCA.Music.PlayerWrapper();
	var m_nextPlayer = new OCA.Music.PlayerWrapper();
	_.extend(this, OC.Backbone.Events);

	this.play = function() {
		m_currentPlayer.play();
	};

	this.pause = function() {
		m_currentPlayer.pause();
	};

	this.stop = function() {
		m_currentPlayer.stop();
	};

	this.isPlaying = function() {
		return m_currentPlayer.isPlaying();
	};

	this.seekingSupported = function() {
		return m_currentPlayer.seekingSupported();
	};

	this.seekMsecs = function(msecs) {
		m_currentPlayer.seekMsecs(msecs);
	};

	this.seek = function(ratio) {
		m_currentPlayer.seek(ratio);
	};

	this.seekForward = function(msecs /*optional*/) {
		m_currentPlayer.seekForward(msecs);
	};

	this.seekBackward = function(msecs /*optional*/) {
		m_currentPlayer.seekForward(msecs);
	};

	this.playPosition = function() {
		return m_currentPlayer.playPosition();
	};

	this.setVolume = function(percentage) {
		m_currentPlayer.setVolume(percentage);
		m_nextPlayer.setVolume(percentage);
	};

	this.setPlaybackRate = function(rate) {
		m_currentPlayer.setPlaybackRate(rate);
		m_nextPlayer.setPlaybackRate(rate);
	};

	this.canPlayMIME = function(mime) {
		return m_currentPlayer.canPlayMIME(mime);
	};

	this.fromURL = function(url, mime) {
		swapPlayer();

		if (m_currentPlayer.getUrl() != url) {
			m_currentPlayer.fromURL(url, mime);
		} else {
			// The player already has the correct URL loaded or being loaded. Ensure the playing starts from the
			// beginning and fire the relevant events.
			if (m_currentPlayer.isReady()) {
				m_self.trigger('ready');
			}
			if (m_currentPlayer.getDuration() > 0) {
				m_self.trigger('duration', m_currentPlayer.getDuration());
			}
			m_currentPlayer.seek(0);
			if (m_currentPlayer.getBufferPercent() > 0) {
				m_self.trigger('buffer', m_currentPlayer.getBufferPercent());
			}
		}
	};

	this.prepareURL = function(url, mime) {
		if (m_nextPlayer.getUrl() != url) {
			m_nextPlayer.fromURL(url, mime);
		}
	};

	function swapPlayer() {
		m_currentPlayer.off();

		[m_currentPlayer, m_nextPlayer] = [m_nextPlayer, m_currentPlayer];

		m_currentPlayer.on('all', function(eventName, arg) {
			m_self.trigger(eventName, arg);
		});
	}
};