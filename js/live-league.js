export default {
    template: `
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
                <div class="siimple-table-row">
                    <div class="siimple-table-cell">
                        {{ team.real_rank }} <i class="fas" :class="getIconClass(team.movement)"></i>
                    </div>
                    <div class="siimple-table-cell">
                        <strong>{{ team.entry_name }}</strong>
                        <span class="siimple-tag siimple-tag--primary margin-left-5" v-if="team.chip != ''">
                            {{ getActiveChipName(team) }}
                        </span><br>
                        {{ team.player_name }}
                    </div>
                    <div class="siimple-table-cell">{{ team.real_event_total }}</div>
                    <div class="siimple-table-cell">{{ team.real_total}}</div>
                    <div class="siimple-table-cell siimple--text-center clickable" style="border-left: 1px solid #cbd8e6;" @click="toggleDetails(team.entry)">
                        <span style="font-size: 25px;">
                            <span v-if="team.expanded"><i class="fa fa-caret-up"></i></span>
                            <span v-else><i class="fa fa-caret-down"></i></span>
                        </span>
                    </div>
                </div>
                <div class="siimple-table-row" v-show="team.expanded" v-for="pick in team.picks">
                    <div class="siimple-table-cell">{{ getPositionInString(pick.position) }}</div>
                    <div class="siimple-table-cell">
                        <strong>{{ pick.name }} {{ getCaptaincyRoleIfAny(pick) }}</strong>
                    </div>
                    <div class="siimple-table-cell">
                        <span class="siimple-table-cell-sortable open-modal" @click="setModalContent(pick)">
                            <strong>{{ pick.points }}</strong> pts
                        </span>
                    </div>
                    <div class="siimple-table-cell"></div>
                    <div class="siimple-table-cell"></div>
                </div>
            </div>
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
    `,
    props: ['team', 'league'],
    data() {
        return {
            sortBy: 'real_total',
            sortDirection: 'desc',
            modalContent: {
                name: '',
                content: {}
            }
        }
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
            }

            return this.league;
        },
    },
    methods: {
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
        getPositionInString(position) {
            switch (position) {
                case 1:
                    return 'GK';
                case 2:
                    return 'DEF';
                case 3:
                    return 'MID';
                case 4:
                    return 'FWD';
                default:
                    return '';
            }
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
            if (team.chip == '') {
                return;
            }

            let chip = '';
            switch(team.chip) {
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
    }
}
