const state = {
	currentPage: 'home',
	currentUser: null,
	csrfToken: null,
	competitions: [],
	myCompetitions: [],
	recentActivity: [],
	admin: {
		initialized: false,
		competitions: [],
		payments: [],
		registrations: [],
	},
	theme: 'dark',
	competitionFilters: {
		query: '',
		status: 'all',
	},
};

const THEME_STORAGE_KEY = 'koneko-theme';

document.addEventListener('DOMContentLoaded', init);

// Event delegation for navigation
document.addEventListener('click', function (e) {
	const navigateTarget = e.target.closest('[data-action="navigate"]');
	if (navigateTarget) {
		e.preventDefault();
		const page = navigateTarget.dataset.page;
		if (page) {
			showPage(page);
			window.location.hash = page;
		}
	}

	const closeModalTarget = e.target.closest('[data-action="close-modal"]');
	if (closeModalTarget) {
		e.preventDefault();
		const modalName = closeModalTarget.dataset.modal;
		if (modalName === 'editCompetitionModal') {
			closeEditCompetitionModal();
		}
	}
});

async function init() {
	try {
		initThemeToggle();
		setupPasswordToggles();
		setupPasswordStrength();
		setupCompetitionFilters();
		setupNavigation();
		wireAuthForms();
		wireProfileEditor();
		wireSignOut();
		setupCompetitionInteractions();
		setupAdminUI();
		initMatrixRain();

		// Race condition: Session refresh vs Timeout
		// Ensures loader doesn't stick for more than 1.5s even if network hangs
		// But waits at least 200ms for smooth animation
		const minLoadTime = new Promise(resolve => setTimeout(resolve, 800));
		const sessionTask = refreshSession();
		const maxWaitTime = new Promise(resolve => setTimeout(resolve, 1500));

		await Promise.all([
			minLoadTime,
			Promise.race([sessionTask, maxWaitTime])
		]);

		applyUserToUI();
	} catch (err) {
		console.error('Initialization error:', err);
	} finally {
		hideLoader();


		const initialPage = window.location.hash.replace('#', '') || 'home';
		if (initialPage.startsWith('reset-password')) {
			const params = new URLSearchParams(initialPage.replace('reset-password', ''));
			const token = params.get('token');
			if (token) {
				showPage('reset-password');
				const tokenInput = document.getElementById('reset-token');
				if (tokenInput) tokenInput.value = token;
			} else {
				showPage('forgot-password');
			}
		} else {
			showPage(initialPage);
		}

		window.addEventListener('hashchange', () => {
			const page = window.location.hash.replace('#', '') || 'home';
			if (page.startsWith('reset-password')) {
				const params = new URLSearchParams(page.replace('reset-password', ''));
				const token = params.get('token');
				if (token) {
					showPage('reset-password');
					const tokenInput = document.getElementById('reset-token');
					if (tokenInput) tokenInput.value = token;
				} else {
					showPage('forgot-password');
				}
			} else {
				showPage(page);
			}
		});
	}
}

function hideLoader() {
	const loader = document.getElementById('page-loader');
	if (loader) {
		loader.classList.add('hidden');
		setTimeout(() => {
			loader.remove();
		}, 200); // Match CSS transition duration
	}
}

function notify(message, type = 'info') {
	let el = document.getElementById('toast');
	if (!el) {
		el = document.createElement('div');
		el.id = 'toast';
		el.className = 'notification';
		document.body.appendChild(el);
	}

	// Set notification type using CSS classes
	el.className = `notification notification-${type}`;
	el.textContent = message;

	clearTimeout(el.timeoutId);
	el.timeoutId = setTimeout(() => {
		el.remove();
	}, 3200);
}

function cacheBustUrl(url, version) {
	if (!url) return null;
	const separator = url.includes('?') ? '&' : '?';
	const token = version !== undefined && version !== null ? version : Date.now();
	return `${url}${separator}_=${token}`;
}

function getStoredTheme() {
	try {
		const stored = localStorage.getItem(THEME_STORAGE_KEY);
		return stored === 'light' ? 'light' : 'dark';
	} catch (err) {
		return 'dark';
	}
}

function applyTheme(theme) {
	state.theme = theme === 'light' ? 'light' : 'dark';
	const body = document.body;
	if (body) {
		body.classList.toggle('theme-light', state.theme === 'light');
		body.classList.toggle('theme-dark', state.theme !== 'light');
		if (state.theme === 'dark') {
			body.style.background = '#000000';
			clearMatrixCanvas();
		} else {
			body.style.background = '';
			clearMatrixCanvas();
		}
	}
	document.documentElement.style.colorScheme = state.theme === 'light' ? 'light' : 'dark';
	try {
		localStorage.setItem(THEME_STORAGE_KEY, state.theme);
	} catch (err) {
	}
	updateThemeToggleIcon();
}

function updateThemeToggleIcon() {
	const toggle = document.getElementById('theme-toggle');
	if (!toggle) return;
	const isLight = state.theme === 'light';
	toggle.innerHTML = isLight ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
	toggle.setAttribute('aria-pressed', isLight ? 'true' : 'false');
	toggle.setAttribute('aria-label', isLight ? 'Switch to dark mode' : 'Switch to light mode');
	toggle.setAttribute('title', isLight ? 'Switch to dark mode' : 'Switch to light mode');
}

function initThemeToggle() {
	const initial = getStoredTheme();
	applyTheme(initial);
	const toggle = document.getElementById('theme-toggle');
	if (toggle) {
		toggle.addEventListener('click', () => {
			const next = state.theme === 'light' ? 'dark' : 'light';
			applyTheme(next);
		});
	}
	updateThemeToggleIcon();
}

function fileToBase64(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = () => {
			const result = typeof reader.result === 'string' ? reader.result : '';
			const base64 = result.split(',')[1];
			if (!base64) {
				reject(new Error('Failed to encode image'));
				return;
			}
			resolve({
				base64,
				mime: file.type || 'application/octet-stream',
			});
		};
		reader.onerror = () => reject(new Error('Failed to read file'));
		reader.readAsDataURL(file);
	});
}

async function buildCompetitionPayload(form) {
	const formData = new FormData(form);
	const payload = {};

	for (const [key, value] of formData.entries()) {
		if (key === 'banner_file') continue;
		if (typeof value === 'string') {
			payload[key] = value.trim();
		} else {
			payload[key] = value;
		}
	}

	const bannerFile = formData.get('banner_file');
	const bannerProvided = bannerFile instanceof File && bannerFile.size > 0;
	const editingExisting = Boolean(form.querySelector('input[name="id"]')?.value);

	if (!bannerProvided) {
		if (!editingExisting) {
			throw new Error('Banner image is required.');
		}
	} else {
		const maxSizeBytes = 2 * 1024 * 1024;
		if (bannerFile.size > maxSizeBytes) {
			throw new Error('Banner image must be smaller than 2MB.');
		}
		const { base64, mime } = await fileToBase64(bannerFile);
		payload.bannerData = base64;
		payload.bannerMime = mime;
	}

	return payload;
}

async function refreshSession() {
	try {
		const data = await apiRequest('get_current_user.php', { requireCsrf: false });
		state.currentUser = data.user ?? null;
		state.csrfToken = data.csrf_token ?? null;
	} catch (err) {
		state.currentUser = null;
		state.csrfToken = null;
	}
}

function applyUserToUI() {
	updateAuthVisibility();
	updateDashboardGreeting();
	updateProfileSummary();
	populateProfileForm();

	if (state.currentUser) {
		refreshRecentActivity();
		refreshMyCompetitions();
	} else {
		state.recentActivity = [];
		renderRecentActivity();
		state.myCompetitions = [];
		renderMyCompetitions();
	}

	renderCompetitionList();
	refreshCompetitions();

	if (state.currentUser?.role === 'admin') {
		refreshAdminData();
	}
}

function updateAuthVisibility() {
	const authed = Boolean(state.currentUser);
	const showWhenAuthed = document.querySelectorAll('[data-auth="protected"]');
	showWhenAuthed.forEach((el) => {
		el.style.display = authed ? '' : 'none';
	});

	const signinLink = document.querySelector('.nav-link[data-page="signin"]');
	const signupLink = document.querySelector('.nav-link[data-page="signup"]');
	const logoutLink = document.querySelector('.nav-link[data-page="logout"]');
	const profileLink = document.querySelector('.nav-link[data-page="profile"]');
	const dashboardLink = document.querySelector('.nav-link[data-page="dashboard"]');
	const adminLink = document.getElementById('admin-link');
	const homeLink = document.querySelector('.nav-link[data-page="home"]');

	// Use CSS classes instead of inline styles for CSP compliance
	if (signinLink) signinLink.classList.toggle('auth-hide', authed);
	if (signupLink) signupLink.classList.toggle('auth-hide', authed);
	if (logoutLink) logoutLink.classList.toggle('auth-hide', !authed);
	if (profileLink) profileLink.classList.toggle('auth-hide', !authed);
	if (dashboardLink) dashboardLink.classList.toggle('auth-hide', !authed);
	if (homeLink) homeLink.classList.toggle('auth-hide', authed);
	if (adminLink) {
		adminLink.classList.toggle('auth-hide', !(authed && state.currentUser?.role === 'admin'));
	}

	const profileMenu = document.getElementById('user-profile-menu');
	if (profileMenu) profileMenu.classList.toggle('auth-hide', !authed);
}

async function refreshRecentActivity() {
	if (!state.currentUser) {
		state.recentActivity = [];
		renderRecentActivity();
		return;
	}

	try {
		const { activities } = await apiRequest('recent_activity.php');
		state.recentActivity = Array.isArray(activities) ? activities : [];
	} catch (err) {
		state.recentActivity = [];
	}

	renderRecentActivity();
}

function renderRecentActivity() {
	const container = document.getElementById('activity-feed');
	if (!container) return;

	if (!state.currentUser) {
		container.innerHTML = '<p class="empty-state">Sign in to see your activity.</p>';
		return;
	}

	if (!state.recentActivity.length) {
		container.innerHTML = '<p class="empty-state">No recent activity yet.</p>';
		return;
	}

	container.innerHTML = state.recentActivity
		.map((activity) => {
			const icon = escapeHtml(getActivityIcon(activity.activity_type));
			const description = escapeHtml(activity.description || '');
			const relativeTime = formatRelativeTime(activity.created_at, activity.created_at_epoch_ms);
			return `
				<div class="activity-item">
					<div class="activity-icon">
						<i class="${icon}"></i>
					</div>
					<div class="activity-content">
						<div class="activity-text">${description}</div>
						<div class="activity-time">${escapeHtml(relativeTime)}</div>
					</div>
				</div>
			`;
		})
		.join('');
}

function getActivityIcon(type = '') {
	const map = {
		'auth.signin': 'fas fa-sign-in-alt',
		'auth.signup': 'fas fa-user-plus',
		'profile.update': 'fas fa-user-edit',
		'profile.avatar.update': 'fas fa-camera',
		'profile.password.change': 'fas fa-key',
		'competition.register': 'fas fa-flag',
		'admin.competition.create': 'fas fa-plus-circle',
		'admin.competition.update': 'fas fa-edit',
		'admin.competition.delete': 'fas fa-trash',
		'admin.payment.update': 'fas fa-receipt',
	};
	return map[type] || 'fas fa-info-circle';
}

function formatRelativeTime(dateString, epochMs) {
	let referenceDateMs = null;
	if (typeof epochMs === 'number' && Number.isFinite(epochMs) && epochMs > 0) {
		referenceDateMs = epochMs;
	} else if (dateString) {
		const parsed = Date.parse(dateString);
		if (!Number.isNaN(parsed)) {
			referenceDateMs = parsed;
		}
	}

	if (referenceDateMs === null) return '';

	const diffMs = Date.now() - referenceDateMs;
	if (diffMs <= 0) return 'Just now';

	const totalMinutes = Math.floor(diffMs / 60000);
	if (totalMinutes <= 0) return 'Just now';

	const minutesInDay = 60 * 24;
	const days = Math.floor(totalMinutes / minutesInDay);
	const remainingMinutes = totalMinutes % minutesInDay;
	const hours = Math.floor(remainingMinutes / 60);
	const minutes = remainingMinutes % 60;

	if (days >= 1) {
		if (hours === 0) {
			return `${days}d ago`;
		}
		return `${days}d ${hours}hr ago`;
	}

	if (hours >= 1) {
		if (minutes === 0) {
			return `${hours}hr ago`;
		}
		return `${hours}hr ${minutes}min ago`;
	}

	if (minutes > 0) {
		return `${minutes}min ago`;
	}

	return 'Just now';
}

function formatWibDateTime(dateString) {
	if (!dateString) return '';
	const date = new Date(dateString);
	if (Number.isNaN(date.getTime())) return '';

	const timeFormatter = new Intl.DateTimeFormat('id-ID', {
		timeZone: 'Asia/Jakarta',
		hour: '2-digit',
		minute: '2-digit',
		hour12: false,
	});
	const dateFormatter = new Intl.DateTimeFormat('id-ID', {
		timeZone: 'Asia/Jakarta',
		year: 'numeric',
		month: 'short',
		day: '2-digit',
	});
	const dateKeyFormatter = new Intl.DateTimeFormat('en-CA', {
		timeZone: 'Asia/Jakarta',
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
	});

	const todayKey = dateKeyFormatter.format(new Date());
	const targetKey = dateKeyFormatter.format(date);
	const timePart = timeFormatter.format(date);

	if (todayKey === targetKey) {
		return `${timePart} WIB`;
	}

	return `${dateFormatter.format(date)}, ${timePart} WIB`;
}

function updateDashboardGreeting() {
	const speechBubble = document.querySelector('#dashboard .speech-bubble .speech-text');

	if (speechBubble) {
		const bubbleName = state.currentUser?.username || state.currentUser?.fullName || 'hacker';
		speechBubble.textContent = state.currentUser
			? `Welcome back, ${bubbleName}!`
			: 'Welcome back, hacker!';
	}
}

function updateProfileSummary() {
	const nameEl = document.querySelector('.profile-name');
	if (nameEl) {
		nameEl.textContent = state.currentUser?.fullName || state.currentUser?.username || 'User';
	}

	const avatar = document.querySelector('.avatar-preview');
	if (avatar) {
		const img = avatar.querySelector('img');
		const avatarUrl = state.currentUser?.avatarUrl
			? cacheBustUrl(state.currentUser.avatarUrl, state.currentUser.avatarVersion)
			: null;
		if (avatarUrl) {
			if (img) {
				img.src = avatarUrl;
			} else {
				avatar.innerHTML = `<img src="${escapeHtml(avatarUrl)}" alt="Avatar">`;
			}
		} else {
			avatar.innerHTML = '<i class="fas fa-user"></i>';
		}
	}

	const basicInfo = document.querySelector('.profile-basic-info');
	if (!basicInfo || !state.currentUser) return;

	const name = basicInfo.querySelector('h3');
	const email = basicInfo.querySelector('p');
	const location = basicInfo.querySelector('p:nth-of-type(2)');
	const bioLine = basicInfo.querySelector('.profile-bio');
	const bioText = basicInfo.querySelector('.bio-text');
	const joined = basicInfo.querySelector('p:nth-of-type(4)');

	if (name) {
		name.textContent = state.currentUser.fullName || state.currentUser.username;
	}
	if (email) {
		email.textContent = state.currentUser.email || '';
	}
	if (location) {
		if (state.currentUser.location) {
			location.style.display = 'block';
			location.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${escapeHtml(state.currentUser.location)}`;
		} else {
			location.style.display = 'none';
		}
	}
	if (bioLine && bioText) {
		if (state.currentUser.bio) {
			bioText.textContent = state.currentUser.bio;
			bioLine.style.display = 'block';
		} else {
			bioLine.style.display = 'none';
		}
	}
	if (joined) {
		const date = state.currentUser.createdAt ? new Date(state.currentUser.createdAt) : null;
		if (date && !Number.isNaN(date.getTime())) {
			joined.innerHTML = `<i class="fas fa-calendar"></i> Joined ${escapeHtml(date.toLocaleDateString())}`;
		} else {
			joined.innerHTML = '<i class="fas fa-calendar"></i> Joined recently';
		}
	}

	const profileSpeech = document.querySelector('#profile .profile-mascot .speech-text');
	if (profileSpeech) {
		const bubbleName = state.currentUser?.username || state.currentUser?.fullName || 'hacker';
		profileSpeech.textContent = `Manage your profile, ${bubbleName}!`;
	}
}

function populateProfileForm() {
	const user = state.currentUser;
	const fullNameInput = document.querySelector('#info-tab input[name="full_name"]');
	const emailInput = document.querySelector('#info-tab input[name="email"]');
	const locationInput = document.querySelector('#info-tab input[name="location"]');
	const bioTextarea = document.querySelector('#info-tab textarea[name="bio"]');
	const charCounter = document.querySelector('#info-tab .char-counter');

	if (!user) {
		[fullNameInput, emailInput, locationInput, bioTextarea].forEach((el) => {
			if (el) el.value = '';
		});
		if (charCounter) charCounter.textContent = '0/500';
		return;
	}

	if (fullNameInput) fullNameInput.value = user.fullName || '';
	if (emailInput) emailInput.value = user.email || '';
	if (locationInput) locationInput.value = user.location || '';
	if (bioTextarea) bioTextarea.value = user.bio || '';
	if (charCounter && bioTextarea) {
		charCounter.textContent = `${bioTextarea.value.length}/500`;
	}
}

function setupNavigation() {
	const navLinks = document.querySelectorAll('.nav-link');
	const navToggle = document.getElementById('nav-toggle');
	const navMenu = document.getElementById('nav-menu');

	navLinks.forEach((link) => {
		link.addEventListener('click', (event) => {
			event.preventDefault();
			const page = link.getAttribute('data-page');
			if (page === 'logout') {
				performSignOut();
				return;
			}
			window.location.hash = page;
			showPage(page);
			if (navMenu) navMenu.classList.remove('active');
		});
	});

	if (navToggle && navMenu) {
		navToggle.addEventListener('click', () => {
			navMenu.classList.toggle('active');
		});
	}
}

function setupCompetitionInteractions() {
	const list = document.getElementById('competitions-list');
	if (list) {
		list.addEventListener('click', handleCompetitionListClick);
	}
}

async function refreshCompetitions() {
	try {
		const { competitions } = await apiRequest('competitions.php');
		state.competitions = Array.isArray(competitions) ? competitions : [];
	} catch (err) {
		state.competitions = [];
	}
	renderCompetitionList();
}

function renderCompetitionList() {
	const container = document.getElementById('competitions-list');
	if (!container) return;

	const { query = '', status = 'all' } = state.competitionFilters || {};
	const normalizedQuery = query.trim().toLowerCase();

	let competitions = Array.isArray(state.competitions) ? [...state.competitions] : [];

	if (normalizedQuery) {
		competitions = competitions.filter((comp) => {
			const haystack = [
				comp.name,
				comp.category,
				comp.difficulty_level,
				comp.status,
			]
				.filter(Boolean)
				.join(' ')
				.toLowerCase();
			return haystack.includes(normalizedQuery);
		});
	}

	if (status !== 'all') {
		competitions = competitions.filter((comp) => {
			const compStatus = String(comp.status || '').toLowerCase();
			switch (status) {
				case 'upcoming':
					return compStatus === 'registration_open';
				case 'ongoing':
					return compStatus === 'ongoing';
				case 'ended':
					return compStatus === 'completed';
				default:
					return true;
			}
		});
	}

	if (!competitions.length) {
		container.innerHTML = '<p class="empty-state">No competitions match your filters yet.</p>';
		return;
	}

	container.innerHTML = competitions
		.map((comp) => {
			const status = String(comp.status || 'upcoming');
			const statusLabel = status.replace(/_/g, ' ');
			const registered = Boolean(comp.is_registered);
			const currentCountRaw = Number(comp.current_participants ?? 0);
			const currentCount = Number.isNaN(currentCountRaw) ? 0 : currentCountRaw;
			const maxCount = comp.max_participants === null || comp.max_participants === undefined ? null : Number(comp.max_participants);
			const hasCapacityLimit = typeof maxCount === 'number' && !Number.isNaN(maxCount) && maxCount > 0;
			const capacityFull = hasCapacityLimit && currentCount >= maxCount;
			const registrationOpen = status === 'registration_open';
			const canRegister = Boolean(state.currentUser && !registered && registrationOpen && !capacityFull);
			const participants = currentCount;
			const maxDisplay = hasCapacityLimit ? `/${maxCount}` : '';
			const bannerSrc = cacheBustUrl(comp.bannerUrl, comp.bannerVersion);
			const contactPerson = comp.contact_person ? escapeHtml(comp.contact_person) : '-';

			let actionHtml = '';
			if (canRegister) {
				actionHtml = `
					<button class="btn btn-primary btn-sm" data-action="register" data-id="${comp.id}">
						<i class="fas fa-flag"></i>
						Register
					</button>
				`;
			} else if (registered) {
				actionHtml = '<span class="status-badge status-registration_open">Registered</span>';
			} else if (capacityFull) {
				actionHtml = '<span class="status-badge status-registration_closed">Full</span>';
			} else if (!state.currentUser) {
				actionHtml = '<span class="status-badge status-upcoming">Sign in to register</span>';
			} else {
				actionHtml = `<span class="status-badge status-${escapeHtml(status)}">${escapeHtml(statusLabel)}</span>`;
			}

			return `
				<div class="competition-card">
					<div class="competition-header">
						<div class="competition-title">${escapeHtml(comp.name || 'Untitled Competition')}</div>
						<span class="status-badge status-${escapeHtml(status)}">${escapeHtml(statusLabel)}</span>
					</div>
					${bannerSrc ? `<div class="competition-banner"><img src="${escapeHtml(bannerSrc)}" alt="Banner"></div>` : ''}
					<p><strong>Category:</strong> ${escapeHtml(comp.category || 'Unknown')}</p>
					<p><strong>Difficulty:</strong> ${escapeHtml(comp.difficulty_level || 'Unknown')}</p>
					<p><strong>Start:</strong> ${escapeHtml(formatDate(comp.start_date))}</p>
					<p><strong>End:</strong> ${escapeHtml(formatDate(comp.end_date))}</p>
					<p><strong>Participants:</strong> ${participants}${maxDisplay}</p>
					<p><strong>Contact:</strong> ${contactPerson}</p>
					${comp.prize_pool ? `<p><strong>Prize:</strong> ${escapeHtml(comp.prize_pool)}</p>` : ''}
					<div class="competition-actions">
						${actionHtml}
					</div>
				</div>
			`;
		})
		.join('');
}

async function refreshMyCompetitions() {
	if (!state.currentUser) {
		state.myCompetitions = [];
		renderMyCompetitions();
		updateJoinedCompetitionsCount();
		return;
	}

	try {
		const { registrations } = await apiRequest('my_competitions.php');
		state.myCompetitions = Array.isArray(registrations) ? registrations : [];
	} catch (err) {
		state.myCompetitions = [];
	}
	renderMyCompetitions();
	updateJoinedCompetitionsCount();
}

function renderMyCompetitions() {
	const container = document.getElementById('dashboard-competitions');
	const ongoingContainer = document.getElementById('dashboard-ongoing');
	if (!container) return;

	if (!state.currentUser) {
		container.innerHTML = '<p class="empty-state">Sign in to track your registered competitions.</p>';
		if (ongoingContainer) ongoingContainer.innerHTML = '';
		return;
	}

	if (!state.myCompetitions.length) {
		container.innerHTML = '<p class="empty-state">You have not joined any competitions yet.</p>';
		if (ongoingContainer) ongoingContainer.innerHTML = '<p class="empty-state">No ongoing competitions right now.</p>';
		return;
	}

	container.innerHTML = state.myCompetitions
		.map((registration) => {
			const compStatus = String(registration.competition_status || 'upcoming');
			const compStatusLabel = compStatus.replace(/_/g, ' ');
			const regStatus = String(registration.registration_status || 'pending');
			const regStatusLabel = regStatus.replace(/_/g, ' ');
			const bannerSrc = cacheBustUrl(registration.bannerUrl, registration.bannerVersion);
			const contactPerson = registration.contact_person ? escapeHtml(registration.contact_person) : '-';
			return `
				<div class="competition-card small">
					<div class="competition-header">
						<div class="competition-title">${escapeHtml(registration.name || 'Competition')}</div>
						<span class="status-badge status-${escapeHtml(compStatus)}">${escapeHtml(compStatusLabel)}</span>
					</div>
					${bannerSrc ? `<div class="competition-banner"><img src="${escapeHtml(bannerSrc)}" alt="Banner"></div>` : ''}
					<p><strong>Registration Status:</strong> ${escapeHtml(regStatusLabel)}</p>
					<p><strong>Team:</strong> ${registration.team_name ? escapeHtml(registration.team_name) : '-'}</p>
					<p><strong>Starts:</strong> ${escapeHtml(formatDate(registration.start_date))}</p>
					<p><strong>Registered:</strong> ${escapeHtml(formatDate(registration.registered_at))}</p>
					<p><strong>Contact:</strong> ${contactPerson}</p>
				</div>
			`;
		})
		.join('');

	if (ongoingContainer) {
		const ongoing = state.myCompetitions.filter(
			(entry) => String(entry.competition_status || '').toLowerCase() === 'ongoing'
		);

		if (!ongoing.length) {
			ongoingContainer.innerHTML = '<p class="empty-state">No ongoing competitions right now.</p>';
		} else {
			ongoingContainer.innerHTML = ongoing
				.map((entry) => {
					const bannerSrc = cacheBustUrl(entry.bannerUrl, entry.bannerVersion);
					const contactPerson = entry.contact_person ? escapeHtml(entry.contact_person) : '-';
					return `
						<div class="competition-card small">
							${bannerSrc ? `<div class="competition-banner"><img src="${escapeHtml(bannerSrc)}" alt="Banner"></div>` : ''}
							<div class="competition-title">${escapeHtml(entry.name || 'Competition')}</div>
							<p><strong>Ends:</strong> ${escapeHtml(formatDate(entry.end_date))}</p>
							<p><strong>Your Team:</strong> ${entry.team_name ? escapeHtml(entry.team_name) : '-'}</p>
							<p><strong>Contact:</strong> ${contactPerson}</p>
						</div>
					`;
				})
				.join('');
		}
	}
}

function updateJoinedCompetitionsCount() {
	const statValue = document.querySelector('.stats-grid .stat-card .stat-value');
	if (!statValue) return;
	const count = state.currentUser ? state.myCompetitions.length : 0;
	statValue.textContent = String(count);
}

function handleCompetitionListClick(event) {
	const button = event.target.closest('button[data-action]');
	if (!button) return;

	const competitionId = Number.parseInt(button.dataset.id, 10);
	if (!competitionId) return;

	if (button.dataset.action === 'register') {
		registerForCompetition(competitionId, button);
	}
}

async function registerForCompetition(competitionId, buttonEl) {
	if (!state.currentUser) {
		notify('Please sign in to register for competitions', 'error');
		showCompetitionAlert('You must sign in to register for competitions.', 'error');
		showPage('signin');
		return;
	}

	const competition = state.competitions.find((comp) => Number(comp.id) === Number(competitionId));
	if (competition) {
		const max = Number(competition.max_participants ?? 0);
		const current = Number(competition.current_participants ?? 0);
		if (Number.isFinite(max) && max > 0 && current >= max) {
			const message = 'Competition has reached the maximum number of participants.';
			notify(message, 'error');
			showCompetitionAlert(message, 'error');
			return;
		}
	}

	const button = buttonEl || document.querySelector(`button[data-action="register"][data-id="${competitionId}"]`);
	const modal = document.getElementById('registrationModal');
	const form = document.getElementById('registrationForm');
	const compIdInput = document.getElementById('regCompetitionId');
	const closeBtn = document.getElementById('closeRegistrationModal');

	if (!modal || !form || !compIdInput) {
		notify('Form pendaftaran tidak tersedia. Silakan refresh halaman.', 'error');
		return;
	}

	compIdInput.value = competitionId;
	form.reset();

	const errorDiv = document.getElementById('registrationError');
	if (errorDiv) errorDiv.style.display = 'none';

	modal.classList.add('active');

	const teamNameInput = document.getElementById('regTeamName');
	if (teamNameInput) setTimeout(() => teamNameInput.focus(), 100);

	const closeModal = () => {
		modal.classList.remove('active');
		if (button) button.disabled = false;
	};

	if (closeBtn) {
		closeBtn.onclick = closeModal;
	}

	modal.onclick = (event) => {
		if (event.target === modal) {
			closeModal();
		}
	};

	form.onsubmit = async (e) => {
		e.preventDefault();
		const teamName = document.getElementById('regTeamName').value.trim();
		const notes = document.getElementById('regNotes').value.trim();

		if (errorDiv) errorDiv.style.display = 'none';

		try {
			if (button) button.disabled = true;
			const submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn) submitBtn.disabled = true;

			const response = await apiRequest('register_competition.php', {
				method: 'POST',
				body: {
					competition_id: competitionId,
					team_name: teamName,
					registration_notes: notes,
				},
			});

			closeModal();
			const successMessage = response.message || 'Successfully registered for competition';
			notify(successMessage, 'success');
			showCompetitionAlert(successMessage, 'success');
			await Promise.allSettled([refreshCompetitions(), refreshMyCompetitions(), refreshRecentActivity()]);
		} catch (err) {
			if (errorDiv) {
				errorDiv.textContent = err.message;
				errorDiv.style.display = 'block';
			} else {
				notify(err.message, 'error');
				showCompetitionAlert(err.message, 'error');
			}
		} finally {
			if (button) button.disabled = false;
			const submitBtn = form.querySelector('button[type="submit"]');
			if (submitBtn) submitBtn.disabled = false;
		}
	};
}

function showPage(pageId) {
	const pages = document.querySelectorAll('.page');
	pages.forEach((page) => page.classList.remove('active'));

	if ((pageId === 'dashboard' || pageId === 'profile') && !state.currentUser) {
		notify('Please sign in to access that page', 'error');
		pageId = 'signin';
	}

	if (pageId === 'admin' && (!state.currentUser || state.currentUser.role !== 'admin')) {
		const message = state.currentUser
			? 'Access denied. Admin privileges required.'
			: 'Please sign in to access the admin panel';
		notify(message, 'error');
		pageId = state.currentUser ? 'home' : 'signin';
	} else if (pageId === 'admin') {
		setActiveAdminTab('competitions');
		refreshAdminData();
	}

	const target = document.getElementById(pageId);
	if (target) {
		target.classList.add('active');
		state.currentPage = pageId;
		setActiveNavLink(pageId);
		setDocumentTitle(pageId);

		if (pageId === 'signup') {
			setTimeout(() => {
				setupPasswordStrength();
			}, 100);
		}
	}
}

function setActiveNavLink(pageId) {
	const navLinks = document.querySelectorAll('.nav-link');
	navLinks.forEach((link) => {
		link.classList.toggle('active', link.getAttribute('data-page') === pageId);
	});
}

function setDocumentTitle(pageId) {
	const titles = {
		home: 'Koneko CTF - Capture The Flag Competition',
		competitions: 'CTF Competitions - Koneko CTF',
		dashboard: 'Dashboard - Koneko CTF',
		profile: 'Profile Management - Koneko CTF',
		help: 'Help Center - Koneko CTF',
		signin: 'Sign In - Koneko CTF',
		signup: 'Sign Up - Koneko CTF',
		'forgot-password': 'Forgot Password - Koneko CTF',
		'reset-password': 'Reset Password - Koneko CTF',
		'not-found': 'Page Not Found - Koneko CTF',
	};
	document.title = titles[pageId] || 'Koneko CTF';
}

function wireAuthForms() {
	const signinForm = document.getElementById('signin-form');
	if (signinForm) {
		signinForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const identifier = signinForm.querySelector('input[name="identifier"]')?.value.trim() || '';
			const password = signinForm.querySelector('input[name="password"]')?.value || '';
			const rememberMe = signinForm.querySelector('input[name="rememberMe"]')?.checked || false;

			if (!identifier || !password) {
				notify('Email/username and password are required', 'error');
				return;
			}

			try {
				const data = await apiRequest('signin.php', {
					method: 'POST',
					body: { identifier, password, rememberMe },
				});
				state.currentUser = data.user;
				if (data.csrf_token) {
					state.csrfToken = data.csrf_token;
				}
				applyUserToUI();
				notify('Signed in successfully', 'success');
				showPage('dashboard');
				window.location.hash = 'dashboard';
			} catch (err) {
				notify(err.message, 'error');
			}
		});
	}

	// Terms of Service and Privacy Policy Modal Logic
	const policyLinks = document.querySelectorAll('.policy-link');
	const policyModal = document.getElementById('policyModal');
	const closePolicyModal = document.getElementById('closePolicyModal');
	const policyModalTitle = document.getElementById('policyModalTitle');
	const policyModalBody = document.getElementById('policyModalBody');

	const policies = {
		tos: {
			title: 'Terms of Service',
			content: `
				<h3>1. Acceptance of Terms</h3>
				<p>By accessing and using Koneko CTF, you accept and agree to be bound by the terms and provision of this agreement.</p>
				
				<h3>2. User Conduct</h3>
				<p>You agree to use the platform only for lawful purposes. You are prohibited from violating any applicable laws, regulations, or third-party rights.</p>
				
				<h3>3. Competition Rules</h3>
				<p>Participants must adhere to the specific rules of each competition. Cheating, sharing flags, or attacking the platform infrastructure is strictly prohibited and will result in immediate disqualification.</p>
				
				<h3>4. Intellectual Property</h3>
				<p>All content provided on this platform is the property of Koneko CTF or its content suppliers and protected by international copyright laws.</p>
				
				<h3>5. Termination</h3>
				<p>We reserve the right to terminate or suspend access to our service immediately, without prior notice or liability, for any reason whatsoever.</p>
			`
		},
		privacy: {
			title: 'Privacy Policy',
			content: `
				<h3>1. Information Collection</h3>
				<p>We collect information you provide directly to us, such as when you create an account, participate in a competition, or communicate with us. This may include your name, email address, and username.</p>
				
				<h3>2. Use of Information</h3>
				<p>We use the information we collect to operate, maintain, and improve our services, to communicate with you, and to manage competitions.</p>
				
				<h3>3. Data Security</h3>
				<p>We implement reasonable security measures to protect your information. However, no security system is impenetrable and we cannot guarantee the security of our systems 100%.</p>
				
				<h3>4. Cookies</h3>
				<p>We use cookies to maintain your session and improve your experience. You can control cookie settings through your browser.</p>
				
				<h3>5. Changes to This Policy</h3>
				<p>We may update this privacy policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>
			`
		}
	};

	if (policyModal && closePolicyModal) {
		const close = () => policyModal.classList.remove('active');

		closePolicyModal.addEventListener('click', close);
		policyModal.addEventListener('click', (e) => {
			if (e.target === policyModal) close();
		});

		policyLinks.forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const type = link.dataset.type;
				if (policies[type]) {
					policyModalTitle.textContent = policies[type].title;
					policyModalBody.innerHTML = policies[type].content;
					policyModal.classList.add('active');
				}
			});
		});
	}

	const signupForm = document.getElementById('signup-form');
	if (signupForm) {
		signupForm.addEventListener('submit', async (event) => {
			event.preventDefault();

			const payload = {
				fullName: signupForm.querySelector('input[name="fullName"]')?.value.trim() || '',
				email: signupForm.querySelector('input[name="email"]')?.value.trim() || '',
				username: signupForm.querySelector('input[name="username"]')?.value.trim() || '',
				password: signupForm.querySelector('input[name="password"]')?.value || '',
				confirmPassword: signupForm.querySelector('input[name="confirmPassword"]')?.value || '',
			};

			if (payload.password !== payload.confirmPassword) {
				notify('Passwords do not match', 'error');
				return;
			}

			try {
				await apiRequest('signup.php', {
					method: 'POST',
					body: payload,
				});
				notify('Account created. Please sign in.', 'success');
				showPage('signin');
				window.location.hash = 'signin';
			} catch (err) {
				notify(err.message, 'error');
			}
		});
	}

	// Forgot Password Form
	const forgotPasswordForm = document.getElementById('forgot-password-form');
	if (forgotPasswordForm) {
		forgotPasswordForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const email = forgotPasswordForm.querySelector('input[name="email"]')?.value.trim() || '';

			if (!email) {
				notify('Email is required', 'error');
				return;
			}

			try {
				await apiRequest('forgot_password.php', {
					method: 'POST',
					body: { email },
				});
				notify('If an account exists with this email, a password reset link has been sent. Please check your inbox.', 'success');
				forgotPasswordForm.reset();
			} catch (err) {
				notify(err.message, 'error');
			}
		});
	}

	// Reset Password Form
	const resetPasswordForm = document.getElementById('reset-password-form');
	if (resetPasswordForm) {
		resetPasswordForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const token = resetPasswordForm.querySelector('input[name="token"]')?.value.trim() || '';
			const newPassword = resetPasswordForm.querySelector('input[name="newPassword"]')?.value || '';
			const confirmPassword = resetPasswordForm.querySelector('input[name="confirmPassword"]')?.value || '';

			if (!token) {
				notify('Invalid reset token', 'error');
				return;
			}

			if (!newPassword || !confirmPassword) {
				notify('Please fill in all fields', 'error');
				return;
			}

			if (newPassword !== confirmPassword) {
				notify('Passwords do not match', 'error');
				return;
			}

			try {
				await apiRequest('reset_password.php', {
					method: 'POST',
					body: { token, newPassword, confirmPassword },
				});
				notify('Password reset successfully. You can now sign in with your new password.', 'success');
				showPage('signin');
				window.location.hash = 'signin';
			} catch (err) {
				notify(err.message, 'error');
			}
		});
	}
}

function wireProfileEditor() {
	const editBtn = document.getElementById('edit-profile-btn');
	const cancelBtn = document.getElementById('cancel-edit-btn');
	const actions = document.getElementById('profile-actions');
	const formInputs = document.querySelectorAll('#info-tab input, #info-tab textarea');
	const saveBtn = actions?.querySelector('.btn-primary');
	const bioTextarea = document.querySelector('#info-tab textarea[name="bio"]');
	const charCounter = document.querySelector('#info-tab .char-counter');

	if (bioTextarea && charCounter) {
		bioTextarea.addEventListener('input', () => {
			charCounter.textContent = `${bioTextarea.value.length}/500`;
		});
	}

	if (editBtn && actions) {
		editBtn.addEventListener('click', () => {
			formInputs.forEach((input) => (input.disabled = false));
			actions.style.display = 'flex';
			editBtn.style.display = 'none';
		});
	}

	if (cancelBtn && editBtn && actions) {
		cancelBtn.addEventListener('click', () => {
			formInputs.forEach((input) => (input.disabled = true));
			actions.style.display = 'none';
			editBtn.style.display = 'inline-flex';
			populateProfileForm();
		});
	}

	if (saveBtn) {
		saveBtn.addEventListener('click', async (event) => {
			event.preventDefault();
			await submitProfileUpdate();
		});
	}

	const avatarButton = document.querySelector('.avatar-upload .btn-outline');
	if (avatarButton) {
		const fileInput = document.createElement('input');
		fileInput.type = 'file';
		fileInput.accept = 'image/*';
		fileInput.style.display = 'none';
		document.body.appendChild(fileInput);

		avatarButton.addEventListener('click', () => fileInput.click());
		fileInput.addEventListener('change', async () => {
			if (!fileInput.files?.length) return;
			await uploadAvatar(fileInput.files[0]);
			fileInput.value = '';
		});
	}

	const passwordForm = document.getElementById('change-password-form');
	if (passwordForm) {
		passwordForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			await submitPasswordChange(passwordForm);
		});
	}
}

function wireSignOut() {
	const menuSignout = document.getElementById('menu-signout');
	if (menuSignout) {
		menuSignout.addEventListener('click', performSignOut);
	}
}

function setupAdminUI() {
	const adminPage = document.getElementById('admin');
	if (!adminPage || state.admin.initialized) return;

	const tabButtons = adminPage.querySelectorAll('.admin-tab');
	tabButtons.forEach((btn) => {
		btn.addEventListener('click', () => {
			const tabKey = btn.dataset.tab;
			if (!tabKey) return;
			setActiveAdminTab(tabKey);
			if (state.currentUser?.role === 'admin') {
				switch (tabKey) {
					case 'competitions':
						fetchAdminCompetitions();
						break;
					case 'payments':
						fetchAdminPayments();
						break;
					case 'registrations':
						fetchAdminRegistrations();
						break;
					default:
						break;
				}
			}
		});
	});

	const addForm = document.getElementById('addCompetitionForm');
	if (addForm) {
		addForm.addEventListener('submit', handleAdminCompetitionCreate);
	}

	const editForm = document.getElementById('editCompetitionForm');
	if (editForm) {
		editForm.addEventListener('submit', handleAdminCompetitionUpdate);
	}

	const modalClose = document.querySelector('#editCompetitionModal .modal-close');
	if (modalClose) {
		modalClose.addEventListener('click', closeEditCompetitionModal);
	}

	const editModal = document.getElementById('editCompetitionModal');
	if (editModal) {
		editModal.addEventListener('click', (event) => {
			if (event.target === editModal) {
				closeEditCompetitionModal();
			}
		});
	}

	const competitionsList = document.getElementById('adminCompetitionsList');
	if (competitionsList) {
		competitionsList.addEventListener('click', handleAdminCompetitionsClick);
	}

	const paymentsBody = document.getElementById('paymentsTableBody');
	if (paymentsBody) {
		paymentsBody.addEventListener('click', handleAdminPaymentsClick);
	}

	state.admin.initialized = true;
	window.closeEditCompetitionModal = closeEditCompetitionModal;
}

function setActiveAdminTab(tabKey) {
	const tabs = document.querySelectorAll('.admin-tab');
	const contents = document.querySelectorAll('.admin-tab-content');
	tabs.forEach((tab) => {
		tab.classList.toggle('active', tab.dataset.tab === tabKey);
	});
	contents.forEach((content) => {
		content.classList.toggle('active', content.id === `admin-${tabKey}Tab`);
	});
}

function validateCompetitionSchedule(data, _alertId, requireAll = false) {
	const showError = (message) => showCompetitionToast(message, 'error');
	const parse = (value) => (value ? new Date(value) : null);
	const startDate = parse(data.start_date);
	const endDate = parse(data.end_date);
	const registrationDeadline = parse(data.registration_deadline);

	const allProvided = startDate && endDate && registrationDeadline;
	if (requireAll && !allProvided) {
		showError('Please provide start date, end date, and registration deadline.');
		return false;
	}

	if (!allProvided) {
		return true;
	}

	const startTime = startDate.getTime();
	const endTime = endDate.getTime();
	const deadlineTime = registrationDeadline.getTime();

	if ([startTime, endTime, deadlineTime].some(Number.isNaN)) {
		showError('Please provide valid date and time values.');
		return false;
	}

	if (startTime >= endTime) {
		showError('End date must be after the start date.');
		return false;
	}

	if (deadlineTime > startTime) {
		showError('Registration deadline must be before the start date.');
		return false;
	}

	if (deadlineTime > endTime) {
		showError('Registration deadline must be before the end date.');
		return false;
	}

	return true;
}

async function handleAdminCompetitionCreate(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const submitBtn = form.querySelector('button[type="submit"]');
	let payload;

	try {
		payload = await buildCompetitionPayload(form);
	} catch (err) {
		showCompetitionToast(err.message || 'Failed to process banner image.', 'error');
		return;
	}

	if (!validateCompetitionSchedule(payload, 'competitionAlert', true)) {
		return;
	}

	try {
		if (submitBtn) submitBtn.disabled = true;
		const response = await apiRequest('admin/manage_competitions.php', {
			method: 'POST',
			body: payload,
		});
		showCompetitionToast(response.message || 'Competition added successfully!', 'success');
		form.reset();
		const fileInput = form.querySelector('input[name="banner_file"]');
		if (fileInput) fileInput.value = '';
		await fetchAdminCompetitions();
		await refreshRecentActivity();
	} catch (err) {
		showCompetitionToast(err.message, 'error');
	} finally {
		if (submitBtn) submitBtn.disabled = false;
	}
}

async function handleAdminCompetitionUpdate(event) {
	event.preventDefault();
	const form = event.currentTarget;
	const submitBtn = form.querySelector('button[type="submit"]');
	let payload;

	try {
		payload = await buildCompetitionPayload(form);
	} catch (err) {
		showCompetitionToast(err.message || 'Failed to process banner image.', 'error');
		return;
	}

	if (!payload.id) {
		showCompetitionToast('Missing competition identifier', 'error');
		return;
	}

	if (!validateCompetitionSchedule(payload, 'competitionAlert')) {
		return;
	}

	try {
		if (submitBtn) submitBtn.disabled = true;
		await apiRequest('admin/manage_competitions.php', {
			method: 'PUT',
			body: payload,
		});
		showCompetitionToast('Competition updated successfully!', 'success');
		const fileInput = form.querySelector('input[name="banner_file"]');
		if (fileInput) fileInput.value = '';
		closeEditCompetitionModal();
		await fetchAdminCompetitions();
		await refreshRecentActivity();
	} catch (err) {
		showCompetitionToast(err.message, 'error');
	} finally {
		if (submitBtn) submitBtn.disabled = false;
	}
}

function handleAdminCompetitionsClick(event) {
	const button = event.target.closest('button[data-action]');
	if (!button) return;
	const competitionId = button.dataset.id;
	if (!competitionId) return;

	if (button.dataset.action === 'edit') {
		openEditCompetitionModal(competitionId);
	}

	if (button.dataset.action === 'delete') {
		deleteAdminCompetition(competitionId);
	}
}

function openEditCompetitionModal(id) {
	const competition = state.admin.competitions.find((comp) => String(comp.id) === String(id));
	if (!competition) {
		notify('Competition not found', 'error');
		return;
	}

	const form = document.getElementById('editCompetitionForm');
	if (!form) return;

	const setValue = (selector, value) => {
		const input = form.querySelector(selector);
		if (!input) return;
		input.value = value ?? '';
	};

	setValue('input[name="id"]', competition.id);
	setValue('input[name="name"]', competition.name || '');
	setValue('textarea[name="description"]', competition.description || '');
	setValue('input[name="start_date"]', formatDateTimeLocal(competition.start_date));
	setValue('input[name="end_date"]', formatDateTimeLocal(competition.end_date));
	setValue('input[name="registration_deadline"]', formatDateTimeLocal(competition.registration_deadline));
	setValue('input[name="max_participants"]', competition.max_participants ?? '');
	setValue('input[name="prize_pool"]', competition.prize_pool || '');
	setValue('input[name="contact_person"]', competition.contact_person || '');

	const difficultySelect = form.querySelector('select[name="difficulty_level"]');
	if (difficultySelect) {
		difficultySelect.value = competition.difficulty_level || 'beginner';
	}

	const categorySelect = form.querySelector('select[name="category"]');
	if (categorySelect) {
		categorySelect.value = competition.category || 'international';
	}

	const rulesField = form.querySelector('textarea[name="rules"]');
	if (rulesField) {
		rulesField.value = competition.rules || '';
	}

	const fileInput = form.querySelector('input[name="banner_file"]');
	if (fileInput) {
		fileInput.value = '';
	}

	const preview = document.getElementById('editBannerPreview');
	if (preview) {
		const bannerSrc = cacheBustUrl(competition.bannerUrl, competition.bannerVersion);
		preview.innerHTML = bannerSrc
			? `<img src="${escapeHtml(bannerSrc)}" alt="Current banner">`
			: '<p class="empty-state">No banner uploaded yet.</p>';
	}

	const modal = document.getElementById('editCompetitionModal');
	if (modal) {
		modal.classList.add('active');
	}
}

function closeEditCompetitionModal() {
	const modal = document.getElementById('editCompetitionModal');
	if (modal) {
		modal.classList.remove('active');
	}
}

async function deleteAdminCompetition(id) {
	if (!window.confirm('Are you sure you want to delete this competition? This will also delete all registrations.')) return;

	try {
		await apiRequest('admin/manage_competitions.php', {
			method: 'DELETE',
			body: { id },
		});
		showCompetitionToast('Competition deleted successfully!', 'success');
		await fetchAdminCompetitions();
		await refreshRecentActivity();
	} catch (err) {
		showCompetitionToast(err.message, 'error');
	}
}

function handleAdminPaymentsClick(event) {
	const button = event.target.closest('button[data-action]');
	if (!button) return;
	const registrationId = button.dataset.id;
	if (!registrationId) return;

	if (button.dataset.action === 'approve') {
		verifyAdminPayment(registrationId, 'paid');
	}

	if (button.dataset.action === 'reject') {
		verifyAdminPayment(registrationId, 'refunded');
	}
}

async function refreshAdminData() {
	if (!state.currentUser || state.currentUser.role !== 'admin') return;

	await Promise.allSettled([
		fetchAdminCompetitions(),
		fetchAdminPayments(),
		fetchAdminRegistrations(),
	]);
}

async function fetchAdminCompetitions() {
	if (!state.currentUser || state.currentUser.role !== 'admin') return;

	try {
		const competitions = await apiRequest('admin/manage_competitions.php');
		state.admin.competitions = Array.isArray(competitions) ? competitions : [];
		renderAdminCompetitions();
	} catch (err) {
		state.admin.competitions = [];
		renderAdminCompetitions();
		showCompetitionToast(err.message, 'error');
	}
}

async function fetchAdminPayments() {
	if (!state.currentUser || state.currentUser.role !== 'admin') return;

	try {
		const payments = await apiRequest('admin/verify_payments.php');
		state.admin.payments = Array.isArray(payments) ? payments : [];
		renderAdminPayments();
	} catch (err) {
		state.admin.payments = [];
		renderAdminPayments();
		showAdminAlert('paymentAlert', err.message, 'error');
	}
}

async function fetchAdminRegistrations() {
	if (!state.currentUser || state.currentUser.role !== 'admin') return;

	try {
		const registrations = await apiRequest('admin/get_registrations.php');
		state.admin.registrations = Array.isArray(registrations) ? registrations : [];
		renderAdminRegistrations();
	} catch (err) {
		state.admin.registrations = [];
		renderAdminRegistrations();
	}
}

function renderAdminCompetitions() {
	const container = document.getElementById('adminCompetitionsList');
	if (!container) return;

	if (!state.admin.competitions.length) {
		container.innerHTML = '<p class="empty-state">No competitions yet.</p>';
		return;
	}

	container.innerHTML = state.admin.competitions
		.map((comp) => {
			const statusLabel = String(comp.status || 'upcoming').replace(/_/g, ' ');
			const participants = comp.current_participants || 0;
			const maxParticipants = comp.max_participants ? `/${comp.max_participants}` : '';
			const bannerSrc = cacheBustUrl(comp.bannerUrl, comp.bannerVersion);
			const contactPerson = comp.contact_person ? escapeHtml(comp.contact_person) : '-';
			return `
				<div class="competition-card">
					<div class="competition-header">
						<div class="competition-title">${escapeHtml(comp.name || 'Untitled Competition')}</div>
						<span class="status-badge status-${escapeHtml(comp.status || 'upcoming')}">${escapeHtml(statusLabel)}</span>
					</div>
					${bannerSrc ? `<div class="competition-banner"><img src="${escapeHtml(bannerSrc)}" alt="Banner"></div>` : ''}
					<p><strong>Category:</strong> ${escapeHtml(comp.category || 'N/A')}</p>
					<p><strong>Difficulty:</strong> ${escapeHtml(comp.difficulty_level || 'N/A')}</p>
					<p><strong>Start:</strong> ${escapeHtml(formatDate(comp.start_date))}</p>
					<p><strong>End:</strong> ${escapeHtml(formatDate(comp.end_date))}</p>
					<p><strong>Participants:</strong> ${participants}${maxParticipants}</p>
					<p><strong>Contact:</strong> ${contactPerson}</p>
					${comp.prize_pool ? `<p><strong>Prize:</strong> ${escapeHtml(comp.prize_pool)}</p>` : ''}
					<div class="competition-actions">
						<button type="button" class="btn btn-sm btn-warning" data-action="edit" data-id="${comp.id}">Edit</button>
						<button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${comp.id}">Delete</button>
					</div>
				</div>
			`;
		})
		.join('');
}

function renderAdminPayments() {
	const tbody = document.getElementById('paymentsTableBody');
	if (!tbody) return;

	if (!state.admin.payments.length) {
		tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No pending payments</td></tr>';
		return;
	}

	tbody.innerHTML = state.admin.payments
		.map((payment) => `
			<tr>
				<td>${escapeHtml(payment.id)}</td>
				<td>
					<strong>${escapeHtml(payment.user_name || 'Unknown')}</strong><br>
					<small>${escapeHtml(payment.user_email || '')}</small>
				</td>
				<td>${escapeHtml(payment.competition_name || '')}</td>
				<td>${payment.team_name ? escapeHtml(payment.team_name) : '-'}</td>
				<td>
					<span class="status-badge ${payment.payment_status === 'paid' ? 'status-registration_open' : 'status-upcoming'}">
						${escapeHtml(payment.payment_status)}
					</span>
				</td>
				<td>${escapeHtml(formatDate(payment.registered_at))}</td>
				<td>
					<button type="button" class="btn btn-sm btn-success" data-action="approve" data-id="${payment.id}">Approve</button>
					<button type="button" class="btn btn-sm btn-danger" data-action="reject" data-id="${payment.id}">Reject</button>
				</td>
			</tr>
		`)
		.join('');
}

function renderAdminRegistrations() {
	const tbody = document.getElementById('registrationsTableBody');
	if (!tbody) return;

	if (!state.admin.registrations.length) {
		tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No registrations yet</td></tr>';
		return;
	}

	tbody.innerHTML = state.admin.registrations
		.map((reg) => `
			<tr>
				<td>${escapeHtml(reg.id)}</td>
				<td>
					<strong>${escapeHtml(reg.user_name || 'Unknown')}</strong><br>
					<small>@${escapeHtml(reg.username || '')}</small>
				</td>
				<td>${escapeHtml(reg.competition_name || '')}</td>
				<td>${reg.team_name ? escapeHtml(reg.team_name) : '-'}</td>
				<td>
					<span class="status-badge ${reg.registration_status === 'approved' ? 'status-registration_open' : 'status-upcoming'}">
						${escapeHtml(reg.registration_status || '')}
					</span>
				</td>
				<td>
					<span class="status-badge ${reg.payment_status === 'paid' ? 'status-registration_open' : 'status-upcoming'}">
						${escapeHtml(reg.payment_status || '')}
					</span>
				</td>
				<td>${escapeHtml(reg.score || 0)}</td>
				<td>${escapeHtml(formatDate(reg.registered_at))}</td>
			</tr>
		`)
		.join('');
}

async function verifyAdminPayment(registrationId, status) {
	try {
		await apiRequest('admin/verify_payments.php', {
			method: 'POST',
			body: {
				registration_id: registrationId,
				payment_status: status,
			},
		});
		const successMessage = status === 'paid' ? 'Payment approved successfully!' : 'Payment rejected successfully!';
		showAdminAlert('paymentAlert', successMessage, 'success');
		await Promise.allSettled([fetchAdminPayments(), fetchAdminRegistrations()]);
		await refreshRecentActivity();
	} catch (err) {
		showAdminAlert('paymentAlert', err.message, 'error');
	}
}

function showCompetitionAlert(message, type = 'info') {
	let stack = document.getElementById('competitionToastContainer');
	if (!stack) {
		stack = document.createElement('div');
		stack.id = 'competitionToastContainer';
		document.body.appendChild(stack);
	}

	const wrapper = document.createElement('div');
	wrapper.className = `competition-toast alert-${escapeHtml(type)}`;

	const icon = document.createElement('span');
	icon.className = 'competition-toast-icon';
	icon.innerHTML = type === 'success' ? '✅' : type === 'error' || type === 'danger' ? '⚠️' : 'ℹ️';

	const text = document.createElement('span');
	text.className = 'competition-toast-message';
	text.textContent = message;

	wrapper.appendChild(icon);
	wrapper.appendChild(text);
	stack.appendChild(wrapper);

	setTimeout(() => {
		wrapper.classList.add('fade-out');
		setTimeout(() => {
			wrapper.remove();
		}, 300);
	}, 2800);
}

function showAdminAlert(elementId, message, type = 'info') {
	const container = document.getElementById(elementId);
	if (!container) return;
	container.innerHTML = `<div class="alert alert-${escapeHtml(type)}">${escapeHtml(message)}</div>`;
	setTimeout(() => {
		if (container.innerHTML.includes(message)) {
			container.innerHTML = '';
		}
	}, 5000);
}

function formatDate(dateString) {
	if (!dateString) return 'N/A';
	const date = new Date(dateString);
	if (Number.isNaN(date.getTime())) return dateString;

	return new Intl.DateTimeFormat('en-US', {
		timeZone: 'Asia/Jakarta',
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
		hour12: false,
		timeZoneName: 'short'
	}).format(date);
}

function formatDateTimeLocal(dateString) {
	if (!dateString) return '';
	const date = new Date(dateString);
	if (Number.isNaN(date.getTime())) return '';

	const options = {
		timeZone: 'Asia/Jakarta',
		year: 'numeric', month: '2-digit', day: '2-digit',
		hour: '2-digit', minute: '2-digit', second: '2-digit',
		hour12: false
	};

	const parts = new Intl.DateTimeFormat('en-US', options).formatToParts(date);
	const part = (type) => parts.find(p => p.type === type).value;

	return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

function escapeHtml(value) {
	if (value === null || value === undefined) return '';
	const div = document.createElement('div');
	div.textContent = String(value);
	return div.innerHTML;
}

async function submitProfileUpdate() {
	const payload = {
		fullName: document.querySelector('#info-tab input[name="full_name"]')?.value.trim() ?? '',
		email: document.querySelector('#info-tab input[name="email"]')?.value.trim() ?? '',
		location: document.querySelector('#info-tab input[name="location"]')?.value.trim() ?? '',
		bio: document.querySelector('#info-tab textarea[name="bio"]')?.value.trim() ?? '',
	};

	try {
		const data = await apiRequest('update_profile.php', {
			method: 'POST',
			body: payload,
		});
		state.currentUser = data.user;
		applyUserToUI();
		notify('Profile updated successfully', 'success');
		const actions = document.getElementById('profile-actions');
		const editBtn = document.getElementById('edit-profile-btn');
		if (actions && editBtn) {
			const formInputs = document.querySelectorAll('#info-tab input, #info-tab textarea');
			formInputs.forEach((input) => (input.disabled = true));
			actions.style.display = 'none';
			editBtn.style.display = 'inline-flex';
		}
	} catch (err) {
		notify(err.message, 'error');
	}
}

async function submitPasswordChange(form) {
	if (!state.currentUser) {
		notify('Please sign in to update your password', 'error');
		return;
	}

	const currentPassword = form.querySelector('input[name="currentPassword"]')?.value || '';
	const newPassword = form.querySelector('input[name="newPassword"]')?.value || '';
	const confirmPassword = form.querySelector('input[name="confirmPassword"]')?.value || '';

	if (!currentPassword || !newPassword || !confirmPassword) {
		notify('Please fill in all password fields', 'error');
		return;
	}

	if (newPassword !== confirmPassword) {
		notify('New passwords do not match', 'error');
		return;
	}

	const submitBtn = form.querySelector('button[type="submit"]');

	try {
		if (submitBtn) submitBtn.disabled = true;
		const data = await apiRequest('update_profile.php', {
			method: 'POST',
			body: {
				currentPassword,
				newPassword,
				confirmPassword,
			},
		});
		form.reset();
		state.currentUser = data.user;
		applyUserToUI();
		notify('Password updated successfully', 'success');
	} catch (err) {
		notify(err.message, 'error');
	} finally {
		if (submitBtn) submitBtn.disabled = false;
	}
}

async function uploadAvatar(file) {
	const formData = new FormData();
	formData.append('avatar', file);

	try {
		const data = await apiRequest('upload_avatar.php', {
			method: 'POST',
			body: formData,
			json: false,
		});
		state.currentUser = data.user;
		applyUserToUI();
		notify('Avatar uploaded successfully', 'success');
	} catch (err) {
		notify(err.message, 'error');
	}
}

async function performSignOut() {
	try {
		await apiRequest('logout.php', { method: 'POST' });
	} catch (err) {
		console.error(err);
	}

	state.currentUser = null;
	state.csrfToken = null;
	state.admin.competitions = [];
	state.admin.payments = [];
	state.admin.registrations = [];
	state.recentActivity = [];
	applyUserToUI();

	// Clear signin form
	const signinForm = document.getElementById('signin-form');
	if (signinForm) {
		const identifierInput = signinForm.querySelector('input[name="identifier"]');
		const passwordInput = signinForm.querySelector('input[name="password"]');
		if (identifierInput) identifierInput.value = '';
		if (passwordInput) passwordInput.value = '';
	}

	notify('Signed out', 'success');
	showPage('signin');
	window.location.hash = 'signin';
}

async function ensureCsrfToken() {
	if (state.csrfToken) {
		return state.csrfToken;
	}

	try {
		const response = await fetch('api/get_current_user.php', {
			credentials: 'include',
		});
		let data = {};
		try {
			data = await response.json();
		} catch (err) {
			data = {};
		}

		if (response.ok) {
			if (Object.prototype.hasOwnProperty.call(data, 'csrf_token')) {
				state.csrfToken = data.csrf_token || null;
			}
			if (Object.prototype.hasOwnProperty.call(data, 'user')) {
				state.currentUser = data.user ?? null;
			}
			return state.csrfToken;
		}
	} catch (err) {
		console.error('Failed to refresh CSRF token', err);
	}

	return null;
}

async function apiRequest(path, options = {}) {
	const controller = new AbortController();
	const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
	const opts = { credentials: 'include', requireCsrf: true, signal: controller.signal, ...options };
	const method = (opts.method || 'GET').toUpperCase();
	const safeMethod = method === 'GET' || method === 'HEAD' || method === 'OPTIONS';

	try {
		if (opts.requireCsrf && !safeMethod) {
			const token = await ensureCsrfToken();
			if (!token) {
				throw new Error('Unable to verify request. Please refresh and try again.');
			}
		}

		const headers = opts.headers || {};
		if (opts.body && opts.json !== false) {
			headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.body);
		} else if (opts.json === false) {
			delete opts.json;
		}

		if (opts.requireCsrf && state.csrfToken) {
			headers['X-CSRF-Token'] = state.csrfToken;
		}

		opts.headers = headers;
		delete opts.requireCsrf;

		const response = await fetch(`api/${path}`, opts);
		clearTimeout(timeoutId);

		let data = {};
		try {
			data = await response.json();
		} catch (err) {
		}

		if (!response.ok) {
			const message = data?.error || `Request failed (${response.status})`;
			throw new Error(message);
		}

		return data;
	} catch (err) {
		clearTimeout(timeoutId);
		throw err;
	}
}

function wireSignOutMenu() {
	const container = document.getElementById('user-profile-menu');
	if (!container) return;

	container.addEventListener('click', (event) => {
		const target = event.target.closest('.dropdown-item');
		if (!target) return;
		const action = target.dataset.action;
		if (action === 'edit-profile') {
			window.location.hash = 'profile';
			showPage('profile');
			const editBtn = document.getElementById('edit-profile-btn');
			editBtn?.click();
		}
	});
}

wireSignOutMenu();

function showCompetitionToast(message, type = 'info') {
	const map = {
		success: 'success',
		error: 'error',
		danger: 'error',
		info: 'info',
		warning: 'error',
	};
	notify(message, map[type] || 'info');
}

function calculatePasswordStrength(password) {
	let score = 0;
	if (!password) return { score: 0, label: 'Weak', color: '#dc3545', percentage: 0 };

	// Client-side estimation (mirrors server logic for instant feedback)
	if (password.length >= 12) score++;
	if (password.length >= 16) score++;
	if (/[A-Z]/.test(password)) score++;
	if (/[a-z]/.test(password)) score++;
	if (/[0-9]/.test(password)) score++;
	if (/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?~`]/.test(password)) score++;

	const common = ['password', '123456', '12345678', 'qwerty', 'admin'];
	if (common.includes(password.toLowerCase())) score = 0;

	let label = 'Weak';
	let color = '#dc3545';
	let percentage = 25;

	if (score <= 2) {
		percentage = 25;
		label = 'Weak';
		color = '#dc3545';
	} else if (score <= 4) {
		percentage = 50;
		label = 'Fair';
		color = '#ffc107';
	} else if (score <= 5) {
		percentage = 75;
		label = 'Good';
		color = '#17a2b8';
	} else {
		percentage = 100;
		label = 'Strong';
		color = '#28a745';
	}

	return { score, label, color, percentage };
}

async function updatePasswordStrength(password, form) {
	if (!form) return;

	const strengthBar = form.querySelector('.strength-fill');
	const strengthText = form.querySelector('.strength-text');

	if (!strengthBar || !strengthText) return;

	if (!password) {
		strengthBar.style.width = '0%';
		strengthText.textContent = 'Password strength: Weak';
		strengthText.style.color = '#dc3545';
		return;
	}

	// Server-side validation (Authoritative)
	try {
		const response = await fetch('api/check_password_strength.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({ password })
		});

		if (response.ok) {
			const data = await response.json();
			strengthBar.style.width = data.percentage + '%';
			strengthBar.style.background = data.color;
			strengthText.textContent = `Password strength: ${data.label}`;
			strengthText.style.color = data.color;
		}
	} catch (e) {
		// Fallback to client-side if server fails/offline
		// (Already handled by immediate update in input listener)
	}
}

function setupPasswordToggles() {
	const toggles = document.querySelectorAll('.password-toggle');
	if (!toggles.length) return;

	toggles.forEach((toggle) => {
		toggle.addEventListener('click', () => {
			const input = toggle.closest('.password-input')?.querySelector('input[type="password"], input[type="text"]');
			if (!input) return;
			const isHidden = input.type === 'password';
			input.type = isHidden ? 'text' : 'password';
			toggle.innerHTML = isHidden ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
			toggle.setAttribute('aria-pressed', String(isHidden));
		});
	});
}

function setupPasswordStrength() {
	// Select password inputs from both signup and reset password forms
	const passwordInputs = document.querySelectorAll('#signup-form input[name="password"], #reset-password-form input[name="newPassword"]');

	if (!passwordInputs.length) {
		setTimeout(setupPasswordStrength, 100);
		return;
	}

	passwordInputs.forEach(passwordInput => {
		let isSetup = passwordInput.dataset.strengthSetup === 'true';
		if (isSetup) return;

		let debounceTimer;
		passwordInput.addEventListener('input', (e) => {
			const val = e.target.value;
			const form = passwordInput.closest('form');

			// 1. Immediate Client-Side Feedback (For "Instant" feel)
			const est = calculatePasswordStrength(val);

			if (form) {
				const strengthBar = form.querySelector('.strength-fill');
				const strengthText = form.querySelector('.strength-text');
				if (strengthBar && strengthText) {
					strengthBar.style.width = est.percentage + '%';
					strengthBar.style.background = est.color;
					strengthText.textContent = `Password strength: ${est.label}`;
					strengthText.style.color = est.color;
				}
			}

			// 2. Debounced Server-Side Validation (For Accuracy/Security)
			// Only needed if we want server-side check, but client-side is usually enough for UI feedback
			// Keeping it for consistency if the backend API supports it
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => {
				updatePasswordStrength(val, form);
			}, 300);
		});

		passwordInput.dataset.strengthSetup = 'true';
		// Initial check
		if (passwordInput.value) {
			passwordInput.dispatchEvent(new Event('input'));
		}
	});
}


function setupCompetitionFilters() {
	const searchInput = document.getElementById('search-input');
	if (searchInput) {
		searchInput.value = state.competitionFilters.query;
		searchInput.addEventListener('input', () => {
			state.competitionFilters.query = searchInput.value.trim().toLowerCase();
			renderCompetitionList();
		});
	}

	const filterButtons = document.querySelectorAll('.filter-btn');
	if (filterButtons.length) {
		filterButtons.forEach((button) => {
			const filter = button.dataset.filter || 'all';
			if (filter === state.competitionFilters.status) {
				button.classList.add('active');
			} else {
				button.classList.remove('active');
			}
			button.addEventListener('click', () => {
				filterButtons.forEach((btn) => btn.classList.remove('active'));
				button.classList.add('active');
				state.competitionFilters.status = filter;
				renderCompetitionList();
			});
		});
	}
}

let matrixCanvas = null;
let matrixCtx = null;
let matrixInterval = null;

function initMatrixRain() {
	const canvas = document.getElementById('matrix-canvas');
	if (!canvas) return;

	matrixCanvas = canvas;
	const ctx = canvas.getContext('2d');
	if (!ctx) return;
	matrixCtx = ctx;

	const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?~`';
	const fontSize = 12;
	let columns = Math.floor(window.innerWidth / fontSize);

	let drops = [];

	function initializeDrops() {
		columns = Math.floor(matrixCanvas.width / fontSize);
		drops = [];
		for (let i = 0; i < columns; i++) {
			drops[i] = Math.random() * -100;
		}
	}

	function resizeCanvas() {
		matrixCanvas.width = window.innerWidth;
		matrixCanvas.height = window.innerHeight;
		initializeDrops();
	}
	resizeCanvas();
	window.addEventListener('resize', resizeCanvas);

	function getRainColor() {
		const isLight = state.theme === 'light';
		return isLight ? 'rgba(0, 0, 0, 0.6)' : 'rgba(0, 180, 200, 0.4)';
	}

	function draw() {
		const isLight = state.theme === 'light';
		matrixCtx.fillStyle = isLight ? 'rgba(248, 250, 252, 0.05)' : 'rgba(0, 0, 0, 0.05)';
		matrixCtx.fillRect(0, 0, matrixCanvas.width, matrixCanvas.height);

		const rainColor = getRainColor();
		matrixCtx.fillStyle = rainColor;
		matrixCtx.font = `${fontSize}px monospace`;

		for (let i = 0; i < drops.length; i++) {
			const text = chars[Math.floor(Math.random() * chars.length)];

			const y = drops[i] * fontSize;
			if (y > 0) {
				matrixCtx.fillText(text, i * fontSize, y);
			}

			if (y > matrixCanvas.height && Math.random() > 0.975) {
				drops[i] = 0;
			}

			drops[i]++;
		}
	}

	if (matrixInterval) {
		clearInterval(matrixInterval);
	}

	matrixInterval = setInterval(draw, 50);
}

function clearMatrixCanvas() {
	if (matrixCanvas && matrixCtx) {
		matrixCtx.clearRect(0, 0, matrixCanvas.width, matrixCanvas.height);
		if (state.theme === 'dark') {
			matrixCtx.fillStyle = '#000000';
			matrixCtx.fillRect(0, 0, matrixCanvas.width, matrixCanvas.height);
		}
	}
}
