// ============================================
// Shelah — API Fetch Wrapper
// ============================================

async function apiFetch(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    credentials: 'include',
    headers: {}
  };
  if (body && method !== 'GET') {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(endpoint, opts);
  const data = await res.json();
  if (!res.ok) {
    if (res.status === 401) {
      window.currentUser = null;
      if (!window.location.pathname.includes('index.html') && window.location.pathname !== '/') {
        window.location.href = '/index.html?login=1';
        return;
      }
    }
    throw { status: res.status, ...data };
  }
  return data;
}

// Auth
async function signup(email, password, displayName) {
  return apiFetch('/api/auth/signup.php', 'POST', { email, password, display_name: displayName });
}
async function login(email, password) {
  return apiFetch('/api/auth/login.php', 'POST', { email, password });
}
async function logout() {
  return apiFetch('/api/auth/logout.php', 'POST');
}
async function getMe() {
  return apiFetch('/api/auth/me.php');
}

// Friends
async function addFriend(email) {
  return apiFetch('/api/friends/add.php', 'POST', { email });
}
async function getFriends() {
  return apiFetch('/api/friends/list.php');
}
async function getFriendRequests() {
  return apiFetch('/api/friends/requests.php');
}
async function respondFriendRequest(friendshipId, action) {
  return apiFetch('/api/friends/respond.php', 'POST', { friendship_id: friendshipId, action });
}

// Outings
async function createOuting(name, outingType, scheduledDate, inviteeIds, description) {
  return apiFetch('/api/outings/create.php', 'POST', { name, outing_type: outingType, scheduled_date: scheduledDate, invitee_ids: inviteeIds, description: description || null });
}
async function getOutings() {
  return apiFetch('/api/outings/list.php');
}
async function getOuting(id) {
  return apiFetch('/api/outings/get.php?id=' + id);
}
async function acceptInvite(outingId) {
  return apiFetch('/api/outings/accept.php', 'POST', { outing_id: outingId });
}
async function inviteToOuting(outingId, userId) {
  return apiFetch('/api/outings/invite.php', 'POST', { outing_id: outingId, user_id: userId });
}
async function getInviteApprovals(outingId) {
  return apiFetch('/api/outings/invite-approvals.php?outing_id=' + outingId);
}
async function approveInvite(outingId, candidateUserId, approved) {
  return apiFetch('/api/outings/approve-invite.php', 'POST', { outing_id: outingId, candidate_user_id: candidateUserId, approved });
}

// Requirements
async function getRequirements(outingId) {
  return apiFetch('/api/requirements/get.php?outing_id=' + outingId);
}
async function saveRequirements(data) {
  return apiFetch('/api/requirements/save.php', 'POST', data);
}

// Recommendations
async function getRecommendations(outingId) {
  return apiFetch('/api/recommendations/get.php?outing_id=' + outingId);
}

// Votes
async function toggleVote(outingId, placeId) {
  return apiFetch('/api/votes/toggle.php', 'POST', { outing_id: outingId, place_id: placeId });
}
async function confirmVotes(outingId) {
  return apiFetch('/api/votes/confirm.php', 'POST', { outing_id: outingId });
}

// Config
async function getMapsKey() {
  return apiFetch('/api/config/maps-key.php');
}

// Dashboard Stats
async function getDashboardStats() {
  return apiFetch('/api/stats/dashboard.php');
}

// Friend Invite Link
async function getInviteLink() {
  return apiFetch('/api/friends/invite-link.php');
}
async function getInviteInfo(code) {
  return apiFetch('/api/friends/invite-info.php?code=' + code);
}
async function sendInviteRequest(code) {
  return apiFetch('/api/friends/invite-accept.php', 'POST', { code });
}
