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
                        <strong>{{ team.team_name }}</strong>
                        <span class="siimple-tag siimple-tag--primary margin-left-5" v-if="team.chip != ''">
                            {{ getActiveChipName(team) }}
                        </span><br>
                        {{ team.player_name }}
                    </div>
                    <div class="siimple-table-cell">{{ team.real_event_total }}</div>
                    <div class="siimple-table-cell">{{ team.real_total}}</div>
                    <div class="siimple-table-cell siimple--text-center clickable" style="border-left: 1px solid #cbd8e6;" @click="toggleDetails(team.id)">
                        <span style="font-size: 25px;">
                            <span v-if="team.expanded"><i class="fa fa-caret-up"></i></span>
                            <span v-else><i class="fa fa-caret-down"></i></span>
                        </span>
                    </div>
                </div>
                <div class="siimple-table-row" v-show="team.expanded" v-for="pick in team.picks">
                    <div class="siimple-table-cell">{{ pick.position }}</div>
                    <div class="siimple-table-cell">
                        <strong>{{ pick.name }}</strong>
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
    props: ['team', 'league', 'players'],
    data() {
        return {
            sortBy: 'real_total',
            sortDirection: 'desc',
            showBreakdownModal: false,
            modalContent: {
                name: '',
                content: {}
            }
        }
    },
    computed: {
        newLeagueTable() {
            let newLeagueTable = [];
            for (var x in this.league) {
                let team = this.league[x];
                newLeagueTable.push({
                    id: team.entry,
                    team_name: team.entry_name,
                    player_name: team.player_name,
                    real_event_total: team.real_event_total,
                    real_total: this.getRealTotal(team),
                    last_rank: team.last_rank,
                    picks: this.getDetailedPlayerData(team.picks),
                    expanded: team.expanded,
                    chip: team.chip
                });
            }

            var that = this;
            newLeagueTable.sort(function(a, b) {
                if (that.sortDirection == 'desc') {
                    return b[that.sortBy] - a[that.sortBy];
                } else {
                    return a[that.sortBy] - b[that.sortBy];
                }
            });

            for (var x in newLeagueTable) {
                newLeagueTable[x].real_rank = parseInt(x) + 1;
                newLeagueTable[x].movement = this.getMovement(newLeagueTable[x]);
            }

            return newLeagueTable;
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
        getDetailedPlayerData(picks) {
            let detailedPlayerData = [];
            for (var x in picks) {
                if (typeof picks[x].points == "undefined") {
                    continue;
                }

                let name = this.players[picks[x].id].name;
                let points = picks[x].points;
                let breakdown = picks[x].breakdown;
                if (picks[x].captain) {
                    if (picks[x].tripleCaptain) {
                        name += ' (TC)';
                    } else {
                        name += ' (C)';
                    }
                }
                if (picks[x].viceCaptain) {
                    name += ' (VC)';
                }

                detailedPlayerData.push({
                    position: this.getPositionInString(this.players[picks[x].id].position),
                    name: name,
                    full_name: this.players[picks[x].id].first_name + ' ' + this.players[picks[x].id].second_name,
                    cost: this.players[picks[x].id].cost,
                    points: points,
                    breakdown: breakdown,
                    bonus: picks[x].bonus,
                    bonus_provisional: picks[x].bonus_provisional
                });
            }

            return detailedPlayerData;
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
                title: pick.name,
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
        getRealTotal(team) {
            return parseInt(team.total - team.event_total + team.real_event_total);
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
