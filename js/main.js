import Countdown from './countdown.js';
import TeamSelect from './select-team.js';
import LeagueSelect from './select-league.js';
import LiveLeague from './live-league.js';

export default {
	template: `
		<div>
			<div class="siimple-spinner siimple-spinner--dark siimple-spinner--large loading-spinner" v-show="loading"></div>
			<div class="siimple-content siimple-content--extra-large" v-if="unsupported">
				<div class="siimple-grid">
					<div class="siimple-grid-col--12">
						<div class="siimple-box siimple-box-icon">
							<i class="fas fa-exclamation-triangle siimple-warning"></i>
						    <div class="siimple-box-title">Your browser is not supported</div>
						    <div class="siimple-box-subtitle">Try again with a non-shit browser, like Chrome, FireFox, Safari or Edge.</div>
						</div>
					</div>
				</div>
			</div>
			<div class="siimple-content siimple-content--extra-large" v-if="updating">
				<div class="siimple-grid">
					<div class="siimple-grid-col--12">
						<div class="siimple-box siimple-box-icon">
							<i class="fas fa-sync-alt siimple-warning"></i>
							<div class="siimple-box-title">The game is updating.</div>
							<div class="siimple-box-subtitle">Please try again in a moment.</div>
						</div>
					</div>
				</div>
			</div>
			<div class="siimple-grid" :class="{loading: loading}" v-if="active">
				<team-select
					v-if="!team"
					@loading="toggleLoading"
					@setTeam="setTeam">
				</team-select>
				<league-select
					v-if="team && !league"
					@loading="toggleLoading"
					@setLeague="setLeague"
					:team="team"
					:leagues="leagues">
				</league-select>
				<live-league
					v-if="team && league"
					@loading="toggleLoading"
					@setLeague="setLeague"
					:team="team"
					:league="league">
				</live-league>
			</div>
			<div class="siimple-grid" v-else>
				<div class="siimple-grid-row">
					<countdown
						@activate="active = true"
						:countdown="countdown">
					</countdown>
				</div>
			</div>
		</div>
	`,
	props: ['unsupported', 'updating', 'countdown'],
	data() {
		return {
			baseURL: '',
			active: true,
			loading: false,
			team: null,
			leagues: null,
			league: null,
			leagueId: null
		}
	},
	mounted() {
		this.active = !this.countdown;
		this.baseURL = 'http://' + preloaded.baseURL + '/fantasy';
		if (preloaded.teamData) {
			this.setTeam(preloaded.teamData);
		}
		if (preloaded.leagueData) {
			this.setLeague(preloaded.leagueData);
		}
	},
	methods: {
		toggleLoading(loading) {
			this.loading = loading;
		},
		setTeam(data) {
			this.team = data.entry;
			this.leagues = data.leagues;
			this.loading = false;
			let url = this.baseURL + '?team=' + this.team.id;
			this.pushNewURL(url);
		},
		setLeague(data, leagueId) {
			this.league = data;
			this.loading = false;
			let url = this.baseURL + '?team=' + this.team.id + '&league=' + data[0].league;
			this.pushNewURL(url);
		},
		poll() {
			var that = this;
			setTimeout(function() {
				if (!that.league || !that.league[0].league) {
					return;
				}

				this.update();
			}, 30000);
		},
		goBack() {
			if (this.league) {
				this.league = null;
				let url = this.baseURL + '?team=' + this.team.id;
				this.pushNewURL(url);
				return;
			}

			if (this.leagues && this.team) {
				this.team = null,
				this.leagues = null;
				this.pushNewURL(this.baseURL);
			}
		},
		pushNewURL(url) {
			history.pushState('', '', url);
		},
	},

	components: {
		Countdown,
		TeamSelect,
		LeagueSelect,
		LiveLeague,
	}
}