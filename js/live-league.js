export default {
    template: `
    <div>
    <div class="siimple-grid-row margin-bottom-10">
        <div class="siimple-btn siimple-btn--grey" @click="goBack" v-show="team"><span class="fas fa-arrow-left"></span> Back</div>
        <div class="siimple-btn siimple-btn--primary siimple--float-right" v-show="team && league" @click="update"><span class="fas fa-sync-alt margin-right-5"></span> Update</div>
        <!-- <img v-show="league" height="30" class="bw margin-top-5" title="Automatically updating league results" src="/fantasy/media/live.gif"> -->
    </div>
    <div class="siimple-grid-row siimple-grid-col--sm-12">
        <div class="siimple-switch siimple-switch--success">
            <input type="checkbox" id="mySwitch" v-model="showBench">
            <label for="mySwitch"></label>
            <div></div>
        </div>
        <div class="siimple-switch-label">Show bench</div>
    </div>
    <div class="siimple-grid-row-fullwidth">
        <div class="siimple-table siimple-table--striped">
            <div class="siimple-table-header" style="padding: 10px; border-bottom: 0!important;">
                <div class="siimple-table-row">
                    <div class="siimple-table-cell">Rank</div>
                    <div class="siimple-table-cell">Team & Manager</div>
                    <div class="siimple-table-cell siimple-table-cell-sortable" title="Live Gameweek Points" @click="sort('real_event_total')">
                        LGWP<i v-show="sortBy == 'real_event_total' && sortDirection == 'asc'" class="fas fa-sort-up"></i>
                        <i v-show="sortBy == 'real_event_total' && sortDirection == 'desc'" class="fas fa-sort-down"></i>
                    </div>
                    <div class="siimple-table-cell siimple-table-cell-sortable" title="Live Total Points" @click="sort('real_total')">
                        LTP<i v-show="sortBy == 'real_total' && sortDirection == 'asc'" class="fas fa-sort-up"></i>
                        <i v-show="sortBy == 'real_total' && sortDirection == 'desc'" class="fas fa-sort-down"></i>
                    </div>
                    <div class="siimple-table-cell"></div>
                </div>
            </div>
            <div class="siimple-table-body" v-for="(team, index) in newLeagueTable">
                <div class="siimple-table-row" :class="{'siimple-table-row-selected': team.isSelectedTeam}">
                    <div class="siimple-table-cell">
                        {{ team.real_rank }} <i class="fas" :class="getIconClass(team.movement)"></i>
                    </div>
                    <div class="siimple-table-cell siimple-table-cell--5">
                        <strong class="block">{{ team.team_name }}</strong>
                        <span class="block">{{ team.player_name }}</span>
                        <span class="siimple-tag siimple-tag--primary" v-if="getActiveChipName(team)">
                            {{ getActiveChipName(team) }}
                        </span>
                    </div>
                    <div class="siimple-table-cell">{{ team.real_event_total }}</div>
                    <div class="siimple-table-cell siimple-table-cell--5">{{ team.real_total}}</div>
                    <div class="siimple-table-cell siimple--text-center clickable" style="border-left: 1px solid #cbd8e6;" @click="toggleDetails(team.entry)">
                        <span style="font-size: 25px;">
                            <span v-if="team.expanded"><i class="fa fa-caret-up"></i></span>
                            <span v-else><i class="fa fa-caret-down"></i></span>
                        </span>
                    </div>
                </div>
                <div class="siimple-table-row" :class="{'faded': pick.benched}" v-show="team.expanded && (!pick.benched || showBench)" v-for="pick in team.picks">
                    <div class="siimple-table-cell">{{ getPositionInString(pick.position, pick.benched) }}</div>
                    <div class="siimple-table-cell siimple-table-cell--5">
                        <strong>{{ pick.name }} {{ getCaptaincyRoleIfAny(pick) }} </strong><span v-if="pick.transferred_in_for"><i class="siimple--color-success fa fa-arrow-alt-circle-up"></i> {{ pick.transferred_in_for }} <i class="siimple--color-error fa fa-arrow-alt-circle-down"></i></span>
                    </div>
                    <div class="siimple-table-cell">
                        <span class="siimple-table-cell-sortable open-modal" @click="setModalContent(pick)">
                            <strong>{{ pick.points }}</strong> pts
                        </span>
                    </div>
                    <div class="siimple-table-cell siimple-table-cell--5">
                        <span class="siimple-tag" :class="getFixtureFormat(fixture, 'class')" v-for="fixture in pick.fixtures">
                            <i class="fas" :class="getFixtureFormat(fixture, 'icon')"></i>
                            {{ getFixtureFormat(fixture, 'text') }}
                        </span></div>
                    <div class="siimple-table-cell"></div>
                </div>
            </div>
        </div>

        <div id="observed-element" class="siimple-text-center">
            <div class="siimple-spinner siimple-spinner--dark siimple-spinner--large" v-show="isLoadingMoreTeams"></div>
        </div>

         <div class="siimple-modal siimple-modal--small" id="modal" style="display: none;">
            <div class="siimple-modal-content">
                <div class="siimple-modal-header">
                    <div class="siimple-modal-header-title">{{ modalContent.title }} £{{ modalContent.cost }}</div>
                    <div class="siimple-modal-header-close" id="modal-close"></div>
                </div>
                <div class="siimple-modal-body padding-0">
                    <div class="siimple-table siimple-table--striped margin-bottom-0">
                        <div class="siimple-table-header" style="padding: 10px; border-bottom: 0!important;">
                            <div class="siimple-table-row">
                                <div class="siimple-table-cell">Statistic</div>
                                <div class="siimple-table-cell">#</div>
                                <div class="siimple-table-cell">Points</div>
                            </div>
                        </div>
                        <div class="siimple-table-body" v-for="(stats, type) in modalContent.content">
                            <div class="siimple-table-row">
                                <div class="siimple-table-cell">{{ stats.name }}</div>
                                <div class="siimple-table-cell">{{ stats.value}}</div>
                                <div class="siimple-table-cell">{{ stats.points }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    `,
    props: ['team', 'league'],
    data() {
        return {
            sortBy: 'real_total',
            sortDirection: 'desc',
            intersectionObserver: null,
            isLoadingMoreTeams: false,
            modalContent: {
                name: '',
                content: {}
            },
            showBench: true
        }
    },
    mounted() {
        this.setIntersectionObserver();
    },
    computed: {
        newLeagueTable() {
            var that = this;
            this.league.sort(function(a, b) {
                if (that.sortDirection == 'desc') {
                    return b[that.sortBy] - a[that.sortBy];
                } else {
                    return a[that.sortBy] - b[that.sortBy];
                }
            });

            for (var x in this.league) {
                this.league[x].real_rank = parseInt(x) + 1;
                this.league[x].movement = this.getMovement(this.league[x]);
                this.league[x].isSelectedTeam = this.league[x].entry == this.team.id;
            }

            return this.league;
        },
        hasMoreTeamsToShow() {
            return !this.league[this.league.length-1].is_last;
        }
    },
    methods: {
        goBack() {
            this.$parent.goBack();
        },
        setIntersectionObserver() {
            let observed = document.getElementById('observed-element');
            let config = {
                rootMargin: '100px 0px',
            };

            let observer = new IntersectionObserver(this.loadMoreTeams, config);
            observer.observe(observed);
            this.intersectionObserver = observer;
        },
        update(more) {
            let page = 1;
            if (more === true) {
                this.isLoadingMoreTeams = true;
                page = Math.ceil((this.league.length + 50) / 50);
            } else {
                this.$emit('loading');
                page = [];
                for (var i = 1; i <= Math.ceil(this.league.length / 50); i++) {
                    page.push(i);
                }

            }

            var payload = {
                teamID: this.team.id,
                leagueId: this.league[0].league,
                info: 'live',
                page: page
            };

            var that = this;
            $.ajax({
                url: '/fantasy/get-data.php',
                data: payload,
                timeout: 20000
            })
            .done(function(data) {
                let result = JSON.parse(data);
                let newLeague = result.data;
                if (more === true) {
                    newLeague = that.league.concat(result.data);
                }
                that.$emit('setLeague', newLeague, payload.leagueId);
            })
            .fail(function(error) {
                if (error.statusText == "Backend fetch failed") {
                    that.update(more);
                    return;
                }
                if (error.statusText == "timeout") {
                    that.$emit('showError', 'Unable to fetch Fantasy data. Please try again.');
                    return;
                }

                let errorData = JSON.parse(error.responseText);
                that.$emit('showError', errorData.error);
            })
            .always(function(data) {
                if (typeof data == "object") {
                    return;
                }
                let result = JSON.parse(data);
                console.log(result.duration);
            });
        },
        loadMoreTeams(entry) {
            if (!entry[0].isIntersecting || this.$parent.isLoadingMoreTeams || !this.hasMoreTeamsToShow) {
                return;
            }

            this.update(true);
        },
        toggleDetails(id) {
            for (var x in this.league) {
                if (id == this.league[x].entry) {
                    this.league[x].expanded = !this.league[x].expanded;
                    break;
                }
            }
        },
        setModalContent(pick) {
            if (pick.bonus) {
                if (pick.bonus_provisional) {
                    pick.breakdown['bonus'] = {
                        name: 'Projected Bonus',
                        value: pick.bonus,
                        points: pick.bonus
                    };
                } else {
                    pick.breakdown.bonus.name = 'Confirmed Bonus';
                }
            }
            this.modalContent = {
                title: pick.fullName,
                cost: pick.cost,
                content: pick.breakdown
            };
        },
        sort(field) {
            this.sortBy = field;
            let direction = 'desc';
            if (this.sortDirection == 'desc') {
                direction = 'asc';
            }

            this.sortDirection = direction;
        },
        getPositionInString(position, benched) {
            var shortPosition = '';
            switch (position) {
                case 1:
                    var shortPosition = 'GK';
                    break;
                case 2:
                    var shortPosition = 'DEF';
                    break;
                case 3:
                    var shortPosition = 'MID';
                    break;
                case 4:
                    var shortPosition = 'FWD';
                    break;
                default:
                    shortPosition = '';
                    break;
            }

            if (benched) {
                // shortPosition = shortPosition + ' (SUB)';
            }

            return shortPosition;
        },
        getCaptaincyRoleIfAny(pick) {
            if (pick.viceCaptain) {
                return '(VC)';
            }

            if (!pick.captain) {
                return '';
            }

            if (pick.tripleCaptain) {
                return '(TC)';
            }

            return '(C)';
        },
        getMovement(team) {
            if (team.real_rank < team.last_rank) {
                return "up";
            }
            if (team.real_rank > team.last_rank) {
                return "down";
            }

            return "same";
        },
        getActiveChipName(team) {
            if (team.active_chip == '') {
                return false;
            }

            let chip = '';
            switch(team.active_chip) {
                case '3xc':
                    chip = 'Triple Captain';
                    break;
                case 'bboost':
                    chip = 'Bench Boost';
                    break;
                case 'freehit':
                    chip = 'Free Hit';
                    break;
                case 'wildcard':
                    chip = 'Wildcard';
                    break;
            }

            return chip += ' Played';
        },
        getFixtureFormat(fixture, property) {
            var data = {};
            if (fixture.finished) {
                data = {
                    text: 'Finished',
                    class: 'siimple-tag--navy',
                    icon: 'fa-check'
                };
                return data[property];
            }

            if (!fixture.started) {
                var text = fixture.time_until_kickoff;
                var tagClass = 'siimple-tag--grey';
                if (!text) {
                    text = 'Starting';
                    tagClass = 'siimple-tag--green';
                }
                data = {
                    text: text,
                    class: tagClass,
                    icon: 'fa-clock'
                };
                return data[property];
            }

            data = {
                text: fixture.minutes+'\'',
                class: 'siimple-tag--green',
                icon: 'fa-stopwatch'
            };
            return data[property];
        },
        getIconClass(movement) {
            switch(movement) {
                case "up":
                    return "fa-caret-up siimple--color-success"
                break;
                case "down":
                    return "fa-caret-down siimple--color-error"
                break;
                case "same":
                    return "fa-circle siimple--color-neutral siimple-icon-small"
                break;
            }
        }
    },
}
