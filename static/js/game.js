/* 瓯江奶茶 - 游戏核心逻辑 */

const GAME_VERSION = '20260706';

// ===== 游戏状态 =====
let gameState = {
    currentNode: 'prologue_start',
    chapter: 0,
    affection: 0,
    items: [],
    endings: [],
    flags: {},
    history: []
};

// ===== 道具定义 =====
const ITEMS = {
    bear: { icon: '🧸', name: '小熊玩偶', desc: '四月二十号深夜桥头，你送给她的那只软乎乎白色小熊' },
    milk_tea: { icon: '🧋', name: '香飘飘包装袋', desc: '她悄悄放在你宿舍楼下那杯温热奶茶的包装袋，你保存了整整三年' },
    umbrella: { icon: '☂️', name: '雨伞', desc: '重逢那晚突降大雨，你和她共撑一把伞走回宿舍' },
    cake: { icon: '🥮', name: '广州特产糕点', desc: '离别前一晚她从广州带来的家乡特产' },
    beer: { icon: '🍺', name: '啤酒', desc: '无数个深夜，你独自坐在宿舍窗边，对着瓯江灯火消磨长夜' }
};

// ===== 剧情数据 =====
const STORY_DATA = {
    // 序章：初来温州，人海相逢
    prologue_start: {
        chapter: 0,
        chapterName: '序章：初来温州，人海相逢',
        text: [
            '盛夏刚过的初春，潮湿闷热的温州裹着陌生水汽，17岁的郑小阳拖着行李箱走进维多利开元大酒店。',
            '前厅后厨人头攒动，各地实习生来来往往，他听不懂本地方言，分不清国一国二包厢，找不到一次性耗材，手足无措站在角落。',
            '手机弹出工作群消息，有人说耗材找不到可以问龚丽冰。'
        ],
        options: [
            { text: '主动添加龚丽冰微信，鼓起勇气求助', next: 'prologue_1a', affection: 3 },
            { text: '自己硬扛，四处乱找，绝不麻烦别人', next: 'prologue_1b', affection: 0 }
        ]
    },
    prologue_1a: {
        chapter: 0,
        chapterName: '序章：初来温州，人海相逢',
        text: [
            '你犹豫半天，发送好友申请，备注"后厨实习生小阳，想问耗材在哪"。',
            '没过几秒通过验证，龚丽冰温柔的消息立刻发来：找不到就先用一次性的，不用硬找。',
            '往后日子，只要工作出错、流程不懂，你都会找她，她永远耐心解答，成了你在温州第一个依靠。'
        ],
        options: [
            { text: '进入第一章', next: 'chapter1_1' }
        ],
        flag: 'added_wechat'
    },
    prologue_1b: {
        chapter: 0,
        chapterName: '序章：初来温州，人海相逢',
        text: [
            '你独自在储物间翻找半小时，耽误摆台进度，被领班轻微批评。',
            '午休时看见龚丽冰路过，主动问你是不是遇到麻烦，主动过来帮你补齐耗材，依旧搭上话，只是少了前期线上聊天铺垫。'
        ],
        options: [
            { text: '进入第一章', next: 'chapter1_1' }
        ]
    },

    // 第一章：后厨朝夕，悄悄动心
    chapter1_1: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '你分到国二包厢，下班时洗碗间已经关灯锁门，成堆餐具没法清洗，慌得手足无措。',
            '你拍照发给龚丽冰，她很快回复：自己简单冲洗也行，实在不行找侯经理开门。'
        ],
        options: [
            { text: '听她建议，去找侯经理沟通开门', next: 'chapter1_1a', affection: 2 },
            { text: '默默蹲在洗碗间门口，等第二天一早处理', next: 'chapter1_1b', affection: -1 }
        ]
    },
    chapter1_1a: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '经理赶来开门，你顺利洗完餐具，晚班结束后龚丽冰特意绕过大堂，给你递一瓶温水安抚。'
        ],
        options: [
            { text: '继续', next: 'chapter1_2' }
        ]
    },
    chapter1_1b: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '蹲到深夜，餐具简单冲洗不干净，第二天被领班问责，龚丽冰得知后午休过来陪你重新整理餐具。'
        ],
        options: [
            { text: '继续', next: 'chapter1_2' }
        ]
    },
    chapter1_2: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '你的专属工作推车莫名不见，收包厢没有工具十分麻烦。',
            '龚丽冰发来消息：我这边有推车，需要可以借你用。'
        ],
        options: [
            { text: '开口借她的推车，日常工作频繁碰面', next: 'chapter1_2a', affection: 3 },
            { text: '婉拒好意，向后厨大叔要闲置旧推车', next: 'chapter1_2b', affection: 0 }
        ]
    },
    chapter1_2a: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '每天和她共用推车，摆台、收垃圾都能碰到，休息时两人会站在走廊闲聊南北家乡差异，你愈发贪恋和她相处的时刻。'
        ],
        options: [
            { text: '继续', next: 'chapter1_3' }
        ]
    },
    chapter1_2b: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '拿到大叔的旧推车，碰面次数变少，只能靠微信聊天缓解孤单，心底的喜欢藏得更深。'
        ],
        options: [
            { text: '继续', next: 'chapter1_3' }
        ]
    },
    chapter1_3: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '深夜收完包厢，窗外下起小雨，你担心她没带伞，主动发消息询问。',
            '她回复随身带了伞，不用担忧。'
        ],
        options: [
            { text: '不打扰，直接回宿舍休息', next: 'chapter1_3a', affection: -1 },
            { text: '借口顺路，走到宿舍楼下等她下班', next: 'chapter1_3b', affection: 3 }
        ]
    },
    chapter1_3a: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '独自走回宿舍，躺在床上反复翻看和她的聊天记录，心里空落落的。'
        ],
        options: [
            { text: '继续', next: 'chapter1_4' }
        ]
    },
    chapter1_3b: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '在楼下等了二十分钟，她提着垃圾袋走来，两人共走一段路回宿舍，路上聊起广东海边的风景，晚风把她的声音吹得很轻。'
        ],
        options: [
            { text: '继续', next: 'chapter1_4' }
        ]
    },
    chapter1_4: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '晚间客人临时加单，留下大量未下单酒水，只有你一个人留下来收拾。',
            '龚丽冰发来消息，满是愧疚：对不住，让你一个人收包厢。'
        ],
        options: [
            { text: '温柔回复没关系，不用在意', next: 'chapter1_4a', affection: 2 },
            { text: '和她吐槽工作很累，倾诉异乡委屈', next: 'chapter1_4b', affection: 1 }
        ]
    },
    chapter1_4a: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '她夸你懂事，第二天上班悄悄给你带小零食。'
        ],
        options: [
            { text: '进入第二章', next: 'chapter2_1' }
        ]
    },
    chapter1_4b: {
        chapter: 1,
        chapterName: '第一章：后厨朝夕，悄悄动心',
        text: [
            '她耐心听你倾诉，慢慢开导你适应酒店高强度工作，心疼你年纪太小干重活。'
        ],
        options: [
            { text: '进入第二章', next: 'chapter2_1' }
        ]
    },

    // 第二章：离别将近，藏不住心意
    chapter2_1: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '四月下旬，所有人都知道龚丽冰实习即将结束，23号就要收拾行李回广州。',
            '你心里越来越慌，想抓住仅剩的相处时间。'
        ],
        options: [
            { text: '继续', next: 'chapter2_2' }
        ]
    },
    chapter2_2: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '22点多下班，你买了一只小熊玩偶，犹豫要不要送给她，发消息让她下楼。',
            '她走到宿舍楼下，你却已经走到远处桥头。'
        ],
        options: [
            { text: '直白告诉她：其实就是想多见你一面', next: 'chapter2_2a', affection: 5, addItem: 'bear' },
            { text: '找借口玩偶行李放不下，暂时放她这里', next: 'chapter2_2b', affection: 2, addItem: 'bear' }
        ]
    },
    chapter2_2a: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '她愣了一下，笑着答应，慢慢走到桥头和你碰面，晚风拂过江面，两人安静站了很久，她看出你眼底藏着不一样的情绪。'
        ],
        options: [
            { text: '继续', next: 'chapter2_3' }
        ]
    },
    chapter2_2b: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '她无奈收下玩偶，回宿舍前，悄悄在你宿舍楼下放了一杯温热香飘飘奶茶，发消息提醒你记得拿。'
        ],
        options: [
            { text: '继续', next: 'chapter2_3', addItem: 'milk_tea' }
        ]
    },
    chapter2_3: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '临走前几天，龚丽冰依旧处处照顾你，提醒你工作服存放位置、包厢耗材摆放，反复叮嘱工作注意事项。',
            '休息间隙她和你闲聊，说起广州的生活，说起自己异地男友。'
        ],
        options: [
            { text: '假装不在意，附和她的话题', next: 'chapter2_3a', affection: 0 },
            { text: '心里酸涩，沉默不怎么说话', next: 'chapter2_3b', affection: -1 }
        ]
    },
    chapter2_3a: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '你强装轻松聊天，心里却清楚你们之间没有可能，离别的焦虑越来越重。'
        ],
        options: [
            { text: '进入第三章', next: 'chapter3_choice' }
        ]
    },
    chapter2_3b: {
        chapter: 2,
        chapterName: '第二章：离别将近，藏不住心意',
        text: [
            '你的低落被她察觉，她温柔安慰你，说以后有缘还会再见。'
        ],
        options: [
            { text: '进入第三章', next: 'chapter3_choice' }
        ]
    },

    // 第三章：坦白心意，温柔拒绝（关键分水岭）
    chapter3_choice: {
        chapter: 3,
        chapterName: '第三章：坦白心意，温柔拒绝',
        text: [
            '龚丽冰离开温州只剩最后两天，你翻遍一整个春天的聊天记录，纠结要不要告白。'
        ],
        options: [
            { text: '选择鼓起勇气告白（主线伤感结局）', next: 'chapter3_confess' },
            { text: '选择藏住心意，绝不告白（留白遗憾支线）', next: 'chapter3_hide' }
        ]
    },
    chapter3_confess: {
        chapter: 3,
        chapterName: '第三章：坦白心意，温柔拒绝',
        text: [
            '4月25日中午，你盯着聊天框，敲下酝酿许久的文字发送："嗯 我喜欢你，我只是想表达我的心意，希望你不要有心里负担。"',
            '几秒后收到回复："谢谢你的喜欢，但是我把你当成学弟哈哈，你还小，以后会遇到更好的。"',
            '你控制不住情绪回复：说实话真的忘不掉你。',
            '为了彻底不让你抱有期待，她坦诚告诉你，自己在广州已经有男朋友。'
        ],
        options: [
            { text: '体面释怀，克制情绪好好道别', next: 'chapter3_accept' },
            { text: '难以接受，短暂沉默不再回复', next: 'chapter3_silent' }
        ]
    },
    chapter3_accept: {
        chapter: 3,
        chapterName: '第三章：坦白心意，温柔拒绝',
        text: [
            '你回复简单的"好"，之后几天正常以同事身份相处，离别那天远远看着她拖着行李箱离开酒店，没有上前打扰。'
        ],
        options: [
            { text: '进入第四章', next: 'chapter4_start' }
        ],
        flag: 'accepted_end'
    },
    chapter3_silent: {
        chapter: 3,
        chapterName: '第三章：坦白心意，温柔拒绝',
        text: [
            '你长时间没有回复消息，之后几天刻意避开和她碰面，她走的那天，你躲在包厢后台，不敢去送行。'
        ],
        options: [
            { text: '进入第四章', next: 'chapter4_start' }
        ],
        flag: 'silent_end'
    },
    chapter3_hide: {
        chapter: 3,
        chapterName: '第三章：坦白心意，温柔拒绝',
        text: [
            '你反复删除打好的告白文字，最终选择只和她聊工作、日常，始终把喜欢藏在心底。',
            '她如期收拾行李离开，临走只留下一杯香飘飘奶茶，你们永远停留在普通学弟学姐的关系，心底的爱意无人知晓。'
        ],
        options: [
            { text: '进入第四章', next: 'chapter4_start' }
        ],
        flag: 'hidden_love',
        addItem: 'milk_tea'
    },

    // 第四章：人走楼空，只剩回忆
    chapter4_start: {
        chapter: 4,
        chapterName: '第四章：人走楼空，只剩回忆',
        text: [
            '龚丽冰离开温州，返回广东广州，酒店再也没有那个温柔开导你的女生。',
            '曾经每天不间断的聊天框彻底沉寂，偶尔手滑点进对话框，你只能匆忙撤回消息，不敢打扰她的生活。',
            '每日繁重的晚班结束，空荡荡的员工宿舍只剩你一人。'
        ],
        options: [
            { text: '拿出啤酒，独自坐在窗边翻看全部聊天记录', next: 'chapter4_beer', addItem: 'beer' },
            { text: '把她送的香飘飘奶茶包装袋收好，安静发呆', next: 'chapter4_keep', addItem: 'milk_tea' }
        ]
    },
    chapter4_beer: {
        chapter: 4,
        chapterName: '第四章：人走楼空，只剩回忆',
        text: [
            '啤酒一罐接一罐，手机里三月到四月几百条聊天记录反复翻阅，洗碗间、包厢、桥头的画面一一浮现，越看越觉得遗憾。'
        ],
        options: [
            { text: '进入终章', next: 'chapter5_start' }
        ]
    },
    chapter4_keep: {
        chapter: 4,
        chapterName: '第四章：人走楼空，只剩回忆',
        text: [
            '你小心翼翼拆开当初那杯香飘飘，把完整包装袋放进收纳盒妥善保存，没有喝酒，只是静静望着窗外瓯江，回想两人相处的细碎瞬间。'
        ],
        options: [
            { text: '进入终章', next: 'chapter5_start' }
        ]
    },

    // 终章：一年之后，瓯江晚风不变（多结局）
    chapter5_start: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '时间一晃过去一整年，你依旧留在温州维多利开元大酒店实习。',
            '曾经分不清包厢、不会操作设备的青涩少年，如今已经熟练包揽所有后厨工作，褪去17岁的懵懂。',
            '某个傍晚，你走到当初和龚丽冰碰面的桥头，手里放着那个保存了一整年的奶茶包装袋。'
        ],
        options: [
            { text: '查看结局', next: 'ending_check' }
        ]
    },
    ending_check: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '一年后的桥头，晚风依旧...'
        ],
        options: [],
        autoRoute: true
    },
    ending1: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '风吹起包装袋，你想起当初直白说出心意却被拒绝的时刻，不后悔坦诚心意，只是遗憾相遇太早、缘分太浅。',
            '往后各自南北，她在广东，你留在温州，仅有一段短暂的春日回忆。'
        ],
        options: [
            { text: '进入续篇', next: 'sequel_1' }
        ]
    },
    ending2: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '你从来没有说过喜欢，所有人都以为你们只是普通同事，只有自己清楚藏了一整年的心动。',
            '这份心意无人知晓，成为独属于自己的秘密。'
        ],
        options: [
            { text: '进入续篇', next: 'sequel_1' }
        ]
    },
    ending3: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '你把奶茶包装袋轻轻收进口袋，望向奔流的瓯江。',
            '感谢这场相遇教会你温柔与成长，虽然没有结果，但不后悔在异乡遇见她，慢慢放下执念，好好往前走。'
        ],
        options: [
            { text: '进入续篇', next: 'sequel_1' }
        ]
    },
    ending4: {
        chapter: 5,
        chapterName: '终章：一年之后，瓯江晚风不变',
        text: [
            '你依旧放不下，每次下班都会绕路走到桥头，频繁翻看聊天记录，偶尔独自喝酒，长久困在这段没有结果的心动里。'
        ],
        options: [
            { text: '进入续篇', next: 'sequel_1' }
        ]
    },

    // 续篇第一章：偶遇契机
    sequel_1: {
        chapter: 6,
        chapterName: '续篇第一章：偶遇契机',
        text: [
            '一年过去，郑小阳18岁，依旧留在温州维多利开元大酒店；龚丽冰早已回到广州，两人几乎断联，唯独那只香飘飘奶茶包装袋被他妥善收在抽屉，手机里完整保存着3-4月所有聊天记录。',
            '这天酒店行政通知，总部安排跨城市实习生交流学习，广州分店一批实习生会来温州驻店一周，名单里赫然出现「龚丽冰」三个字。'
        ],
        options: [
            { text: '主动找行政打听她的住宿、排班，想提前制造偶遇', next: 'sequel_1a', affection: 2 },
            { text: '假装不在意，照常干活，顺其自然不刻意靠近', next: 'sequel_1b', affection: 0 }
        ]
    },
    sequel_1a: {
        chapter: 6,
        chapterName: '续篇第一章：偶遇契机',
        text: [
            '你找人事问清她住在员工宿舍3栋，班次和你错开半天，特意调整自己休息时间，蹲在宿舍楼下等她。',
            '傍晚时分，熟悉的广东口音传来，她拖着行李箱走来，看见你明显愣了一下。',
            '她笑着打招呼："小阳？你还在这边实习啊。"'
        ],
        options: [
            { text: '继续', next: 'sequel_2' }
        ]
    },
    sequel_1b: {
        chapter: 6,
        chapterName: '续篇第一章：偶遇契机',
        text: [
            '你压下心里的波澜，正常完成摆台、收包厢工作。',
            '次日午间食堂吃饭，她主动坐到你对面搭话，两人被动开启聊天，氛围平淡温和。'
        ],
        options: [
            { text: '继续', next: 'sequel_2' }
        ]
    },

    // 续篇第二章：重逢相处，旧意翻涌
    sequel_2: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '重逢这一周，你们恢复短暂的日常接触，像去年一样聊工作、聊南北生活，但彼此都清楚中间隔着告白、异地男友、分离的隔阂。',
            '某天夜班结束突降大雨，她没带伞，你手里正好有两把伞。'
        ],
        options: [
            { text: '直接把一把伞送给她，自己淋雨回宿舍', next: 'sequel_2a', affection: 1 },
            { text: '借口顺路，和她共撑一把伞走回宿舍桥头', next: 'sequel_2b', affection: 5, addItem: 'umbrella' }
        ]
    },
    sequel_2a: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '她再三道谢，分开撑伞各自回楼栋，那晚她微信给你发了一句"今天麻烦你啦"，简短客气。'
        ],
        options: [
            { text: '继续', next: 'sequel_3' }
        ]
    },
    sequel_2b: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '狭小伞下距离很近，能闻到她身上淡淡的香味，路过去年分开的桥头，两人停下脚步沉默几秒。',
            '她主动提起去年临走送你的香飘飘："当初那杯奶茶你喝完了吗？"'
        ],
        options: [
            { text: '继续', next: 'sequel_3' }
        ]
    },
    sequel_3: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '交流学习安排了半天休息日，她问你温州有什么好玩的江边小路。'
        ],
        options: [
            { text: '带她去酒店22楼观景露台，你们去年待过的地方', next: 'sequel_3a', affection: 5 },
            { text: '带她去市区商场，单纯逛吃避开回忆场景', next: 'sequel_3b', affection: 1 }
        ]
    },
    sequel_3a: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '落地窗外瓯江夜景和一年前一模一样，晚风依旧。',
            '她主动提起当初你深夜表白的事："那时候我刚谈恋爱，不敢耽误你，才把你当学弟。"'
        ],
        options: [
            { text: '释怀坦然，告诉她我早就慢慢想开了', next: 'sequel_3a1', affection: 2 },
            { text: '坦白内心，其实这一年我还是经常想起你', next: 'sequel_3a2', affection: 3 }
        ],
        flag: 'terrace_talk'
    },
    sequel_3a1: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '你释怀地笑了笑，说早已慢慢想开了。她松了口气，两人安静地看着江面灯火。'
        ],
        options: [
            { text: '继续', next: 'sequel_4' }
        ]
    },
    sequel_3a2: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '你坦白内心，她听完沉默良久，轻声说对不起。桥头晚风吹乱了她的头发。'
        ],
        options: [
            { text: '继续', next: 'sequel_4' }
        ]
    },
    sequel_3b: {
        chapter: 6,
        chapterName: '续篇第二章：重逢相处，旧意翻涌',
        text: [
            '全程只聊美食、酒店工作、广东和河南的差异，完全不触碰当年告白、心动的过往，全程维持普通学弟学姐距离。'
        ],
        options: [
            { text: '继续', next: 'sequel_4' }
        ],
        flag: 'shopping_route'
    },

    // 续篇第三章：二次分别，两种心结
    sequel_4: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '一周交流学习转瞬结束，龚丽冰要返程广州，这次分别比上次更让人煎熬。'
        ],
        options: [
            { text: '继续', next: 'sequel_5' }
        ]
    },
    sequel_5: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '离别前一晚...'
        ],
        options: [],
        autoRoute: true
    },
    sequel_5a: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '她单独约你在桥头见面，带来广州特产糕点。'
        ],
        options: [
            { text: '再次直白说出心意，问她如今有没有机会', next: 'sequel_5a1', affection: 3 },
            { text: '彻底放下，只祝她平安顺遂，不再提喜欢', next: 'sequel_5a2', affection: 5, addItem: 'cake' }
        ]
    },
    sequel_5a1: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '她摇摇头，语气无奈："我和男朋友还在一起，没办法回应你的心意，你值得更好的本地人，不用困在我身上。"',
            '你强撑微笑送走她，回到宿舍翻出奶茶包装袋，买了一箱啤酒，整夜喝酒翻看聊天记录。'
        ],
        options: [
            { text: '继续', next: 'sequel_6' }
        ],
        flag: 'second_confess_rejected'
    },
    sequel_5a2: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '你收下糕点，笑着和她告别："当初遇见你很开心，祝你在广州一切顺利，以后不用挂念我。"',
            '她松了口气，和你好好拥抱道别，没有留下沉重的氛围。'
        ],
        options: [
            { text: '继续', next: 'sequel_6' }
        ],
        flag: 'let_go',
        addItem: 'cake'
    },
    sequel_5b: {
        chapter: 6,
        chapterName: '续篇第三章：二次分别，两种心结',
        text: [
            '分别当天只有简单微信道别，没有线下单独见面，她只发了一句"我回广州啦，照顾好自己"，你简单回复，再无多余交流。'
        ],
        options: [
            { text: '继续', next: 'sequel_6' }
        ],
        flag: 'distant_goodbye'
    },

    // 续篇第四章：重逢后的漫长空窗
    sequel_6: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '龚丽冰走后，生活回归原样，但这次重逢打乱了你勉强平静的生活。'
        ],
        options: [
            { text: '继续', next: 'sequel_7' }
        ]
    },
    sequel_7: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '往后几个月...'
        ],
        options: [],
        autoRoute: true
    },
    sequel_7a: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '你时常失眠，下班依旧会独自买酒坐在宿舍窗边。抽屉里的奶茶包装袋、手机里上千条聊天记录反复翻看，偶尔忍不住点开她的微信输入文字，又全部删除。'
        ],
        options: [
            { text: '删除全部聊天记录，丢掉奶茶包装袋，强制断念', next: 'sequel_7a1' },
            { text: '保留所有回忆，任由自己沉溺过去', next: 'sequel_7a2', flag: 'still_hopeless' }
        ]
    },
    sequel_7a1: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '删掉聊天记录、扔掉保存一年的包装袋，刚开始空虚难熬，慢慢强迫自己专注工作，减少独处胡思乱想。'
        ],
        options: [
            { text: '进入续篇终章', next: 'final_start' }
        ],
        flag: 'forced_move_on'
    },
    sequel_7a2: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '所有回忆全部保留，每逢节假日、雨天、夜班下班，都会想起和她相处的日子，一直无法接纳新的人。'
        ],
        options: [
            { text: '进入续篇终章', next: 'final_start' }
        ]
    },
    sequel_7b: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '你把香飘飘包装袋平整收纳进盒子，不再刻意翻看聊天记录，上班认真提升技能，休息时和其他实习生结伴出门散心。'
        ],
        options: [
            { text: '主动屏蔽她的朋友圈，减少关注', next: 'sequel_7b1', flag: 'blocked' },
            { text: '保留好友，偶尔看见她动态，内心毫无波澜', next: 'sequel_7b2', flag: 'peaceful' }
        ]
    },
    sequel_7b1: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '屏蔽动态，彻底减少关注，全身心投入生活，慢慢走出这段遗憾。'
        ],
        options: [
            { text: '进入续篇终章', next: 'final_start' }
        ]
    },
    sequel_7b2: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '不屏蔽、不删除，看见她和男友的合照内心平静，真心祝福她，这段心动变成青春里温柔的回忆。'
        ],
        options: [
            { text: '进入续篇终章', next: 'final_start' }
        ]
    },
    sequel_7c: {
        chapter: 7,
        chapterName: '续篇第四章：重逢后的漫长空窗',
        text: [
            '没有剧烈的情绪波动，只是偶尔想起那个广东女孩，心里空落落的。你开始尝试把更多精力投入工作，下班后和同事一起打球、聚餐。'
        ],
        options: [
            { text: '进入续篇终章', next: 'final_start' }
        ]
    },

    // 续篇终章：三年时限·四大最终结局
    final_start: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        text: [
            '时间跳转至三年后，你20岁，已经从实习生转正，升为酒店餐饮领班，不再是当年手足无措的17岁少年。'
        ],
        options: [
            { text: '查看最终结局', next: 'final_ending' }
        ]
    },
    final_ending: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        text: [
            '三年后的今天...'
        ],
        options: [],
        autoRoute: true
    },
    final_ending1: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        ending: true,
        endingType: 'regret',
        text: [
            '你依旧留在温州，岗位越来越好，身边也有人介绍同龄异性，但你全部拒绝。',
            '抽屉里的奶茶包装袋保存三年，手机里备份着当年所有聊天记录。',
            '偶尔路过桥头，还是会停下吹风，心里清楚这辈子很难放下那个广东来的20岁女孩，爱意永久藏在心底，孤身一人。'
        ],
        endingData: {
            title: '执念余生',
            description: '三年过去，你依然困在那段没有结果的心动里，无法接纳新的人。那份爱意成为此生执念，孤身一人。',
            affection: 0,
            items: ['milk_tea']
        }
    },
    final_ending2: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        ending: true,
        endingType: 'heal',
        text: [
            '你收拾旧物时，将奶茶包装袋、当年的聊天截图全部打包收进储物箱，不再时常翻看。',
            '工作稳定，认识了性格合拍的本地女生，慢慢开启新的生活。',
            '偶尔想起龚丽冰，只剩淡淡的感谢，感谢初春瓯江边那场短暂相遇，教会你如何去喜欢一个人。'
        ],
        endingData: {
            title: '和解告别',
            description: '你把回忆妥善安放，慢慢开启新生活。那段心动变成温柔的感谢，治愈了往后岁月。',
            affection: 0,
            items: ['milk_tea', 'cake']
        }
    },
    final_ending3: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        ending: true,
        endingType: 'resolute',
        text: [
            '你删除微信所有聊天记录，清理掉奶茶包装袋，彻底切断和她相关的一切痕迹。',
            '后来换了城市工作，彻底离开温州，再也不会接触和广州、开元酒店相关的人和事，这段心动彻底尘封，再也不会主动想起。'
        ],
        endingData: {
            title: '山水不相逢',
            description: '你选择彻底切断一切痕迹，离开温州，让那段心动永远尘封。决绝，也是一种自我保护。',
            affection: 0,
            items: []
        }
    },
    final_ending4: {
        chapter: 8,
        chapterName: '续篇终章：三年时限·四大最终结局',
        ending: true,
        endingType: 'perfect',
        text: [
            '三年间你们偶尔微信简单问候，只聊工作近况，不谈及情爱，变成远距离的老朋友。',
            '你拥有安稳的生活，她在广州感情稳定。',
            '某次出差路过广州，两人约简单吃一顿饭，平静聊起当年酒店实习的日子，没有暧昧、没有遗憾，只剩跨越南北的温柔知己情谊，香飘飘的故事成为两人心照不宣的青春秘密。'
        ],
        endingData: {
            title: '遥遥知己·隐藏完美支线',
            description: '你们成为跨越南北的温柔知己，没有暧昧，没有遗憾，香飘飘的故事成为心照不宣的青春秘密。',
            affection: 0,
            items: ['milk_tea', 'cake', 'umbrella']
        }
    }
};

// ===== 小说内容 =====
const NOVEL_DATA = [
    {
        title: '序章：初来温州，人海相逢',
        content: `
            盛夏刚过的初春，潮湿闷热的温州裹着陌生水汽，17岁的郑小阳拖着行李箱走进维多利开元大酒店。

            前厅后厨人头攒动，各地实习生来来往往。他听不懂本地方言，分不清国一国二包厢，找不到一次性耗材，手足无措站在角落。

            手机弹出工作群消息，有人说耗材找不到可以问龚丽冰。

            郑小阳犹豫半天，发送好友申请，备注"后厨实习生小阳，想问耗材在哪"。没过几秒通过验证，龚丽冰温柔的消息立刻发来：找不到就先用一次性的，不用硬找。

            往后日子，只要工作出错、流程不懂，他都会找她，她永远耐心解答，成了他在温州第一个依靠。
        `
    },
    {
        title: '第一章：后厨朝夕，悄悄动心',
        content: `
            郑小阳分到国二包厢，下班时洗碗间已经关灯锁门，成堆餐具没法清洗，慌得手足无措。他拍照发给龚丽冰，她很快回复：自己简单冲洗也行，实在不行找侯经理开门。

            经理赶来开门，他顺利洗完餐具，晚班结束后龚丽冰特意绕过大堂，给他递一瓶温水安抚。

            后来他的专属工作推车莫名不见，收包厢没有工具十分麻烦。龚丽冰发来消息：我这边有推车，需要可以借你用。

            每天和她共用推车，摆台、收垃圾都能碰到，休息时两人会站在走廊闲聊南北家乡差异，他愈发贪恋和她相处的时刻。

            深夜收完包厢，窗外下起小雨，他担心她没带伞，借口顺路走到宿舍楼下等她下班。在楼下等了二十分钟，她提着垃圾袋走来，两人共走一段路回宿舍，路上聊起广东海边的风景，晚风把她的声音吹得很轻。

            某个晚间客人临时加单，留下大量未下单酒水，只有郑小阳一个人留下来收拾。龚丽冰发来消息，满是愧疚：对不住，让你一个人收包厢。他温柔回复没关系，她夸他懂事，第二天上班悄悄给他带小零食。

            心动的种子，在那个潮湿的温州春天，悄悄发了芽。
        `
    },
    {
        title: '第二章：离别将近，藏不住心意',
        content: `
            四月下旬，所有人都知道龚丽冰实习即将结束，23号就要收拾行李回广州。郑小阳心里越来越慌，想抓住仅剩的相处时间。

            22点多下班，他买了一只小熊玩偶，犹豫要不要送给她，发消息让她下楼。她走到宿舍楼下，他却已经走到远处桥头。

            "直白告诉你吧，其实就是想多见你一面。"

            她愣了一下，笑着答应，慢慢走到桥头和他碰面，晚风拂过江面，两人安静站了很久，她看出他眼底藏着不一样的情绪。

            临走前几天，龚丽冰依旧处处照顾他，提醒他工作服存放位置、包厢耗材摆放，反复叮嘱工作注意事项。休息间隙她和他闲聊，说起广州的生活，说起自己异地男友。郑小阳心里酸涩，沉默不怎么说话，他的低落被她察觉，她温柔安慰他，说以后有缘还会再见。

            离别的钟声，越来越近了。
        `
    },
    {
        title: '第三章：坦白心意，温柔拒绝',
        content: `
            龚丽冰离开温州只剩最后两天，郑小阳翻遍一整个春天的聊天记录，纠结要不要告白。

            4月25日中午，他盯着聊天框，敲下酝酿许久的文字发送："嗯 我喜欢你，我只是想表达我的心意，希望你不要有心里负担。"

            几秒后收到回复："谢谢你的喜欢，但是我把你当成学弟哈哈，你还小，以后会遇到更好的。"

            他控制不住情绪回复：说实话真的忘不掉你。为了彻底不让他抱有期待，她坦诚告诉他，自己在广州已经有男朋友。

            他回复简单的"好"，之后几天正常以同事身份相处，离别那天远远看着她拖着行李箱离开酒店，没有上前打扰。

            风吹起奶茶包装袋，他想起当初直白说出心意却被拒绝的时刻，不后悔坦诚心意，只是遗憾相遇太早、缘分太浅。往后各自南北，她在广东，他留在温州，仅有一段短暂的春日回忆。
        `
    },
    {
        title: '第四章：人走楼空，只剩回忆',
        content: `
            龚丽冰离开温州，返回广东广州，酒店再也没有那个温柔开导他的女生。曾经每天不间断的聊天框彻底沉寂，偶尔手滑点进对话框，郑小阳只能匆忙撤回消息，不敢打扰她的生活。

            每日繁重的晚班结束，空荡荡的员工宿舍只剩他一人。他小心翼翼拆开当初那杯香飘飘，把完整包装袋放进收纳盒妥善保存，没有喝酒，只是静静望着窗外瓯江，回想两人相处的细碎瞬间。

            啤酒一罐接一罐的深夜也有过，手机里三月到四月几百条聊天记录反复翻阅，洗碗间、国二包厢、桥头的画面一一浮现，越看越觉得遗憾。

            时间一晃过去一整年，郑小阳依旧留在温州维多利开元大酒店。曾经分不清包厢、不会操作设备的青涩少年，如今已经熟练包揽所有后厨工作，褪去17岁的懵懂。

            某个傍晚，他走到当初和龚丽冰碰面的桥头，手里放着那个保存了一整年的奶茶包装袋。一年后的桥头，晚风依旧。
        `
    },
    {
        title: '续篇：一年之后，重逢与抉择',
        content: `
            一年过去，郑小阳18岁，依旧留在温州维多利开元大酒店；龚丽冰早已回到广州，两人几乎断联，唯独那只香飘飘奶茶包装袋被他妥善收在抽屉，手机里完整保存着3-4月所有聊天记录。

            这天酒店行政通知，总部安排跨城市实习生交流学习，广州分店一批实习生会来温州驻店一周，名单里赫然出现「龚丽冰」三个字。

            郑小阳找人事问清她住在员工宿舍3栋，班次和他错开半天，特意调整自己休息时间，蹲在宿舍楼下等她。傍晚时分，熟悉的广东口音传来，她拖着行李箱走来，看见他明显愣了一下。她笑着打招呼："小阳？你还在这边实习啊。"

            重逢这一周，他们恢复短暂的日常接触，像去年一样聊工作、聊南北生活，但彼此都清楚中间隔着告白、异地男友、分离的隔阂。某天夜班结束突降大雨，她没带伞，他借口顺路，和她共撑一把伞走回宿舍桥头。狭小伞下距离很近，路过去年分开的桥头，两人停下脚步沉默几秒。她主动提起去年临走送他的香飘飘："当初那杯奶茶你喝完了吗？"

            交流学习安排了半天休息日，他带她去酒店22楼观景露台。落地窗外瓯江夜景和一年前一模一样，晚风依旧。她主动提起当初他深夜表白的事："那时候我刚谈恋爱，不敢耽误你，才把你当学弟。"

            郑小阳坦白内心，其实这一年他还是经常想起她。她听完沉默良久，轻声说对不起。桥头晚风吹乱了她的头发。

            一周交流学习转瞬结束，龚丽冰要返程广州，这次分别比上次更让人煎熬。离别前一晚，她单独约他在桥头见面，带来广州特产糕点。他彻底放下，只祝她平安顺遂，不再提喜欢。他收下糕点，笑着和她告别："当初遇见你很开心，祝你在广州一切顺利，以后不用挂念我。"她松了口气，和他好好拥抱道别。
        `
    },
    {
        title: '续篇终章：三年时限，各自归途',
        content: `
            龚丽冰走后，生活回归原样，但这次重逢打乱了郑小阳勉强平静的生活。

            他把香飘飘包装袋平整收纳进盒子，不再刻意翻看聊天记录，上班认真提升技能，休息时和其他实习生结伴出门散心。不屏蔽、不删除，看见她和男友的合照内心平静，真心祝福她，这段心动变成青春里温柔的回忆。

            时间跳转至三年后，郑小阳20岁，已经从实习生转正，升为酒店餐饮领班，不再是当年手足无措的17岁少年。

            三年间他们偶尔微信简单问候，只聊工作近况，不谈及情爱，变成远距离的老朋友。他拥有安稳的生活，她在广州感情稳定。某次出差路过广州，两人约简单吃一顿饭，平静聊起当年酒店实习的日子，没有暧昧、没有遗憾，只剩跨越南北的温柔知己情谊，香飘飘的故事成为两人心照不宣的青春秘密。

            瓯江的水，缓缓流淌。那年17岁的少年，终于学会了如何把心动酿成回忆，如何把遗憾变成成长的养分。

            有些相遇，不是为了相守，而是为了在彼此的生命里，留下最温柔的一笔。
        `
    }
];

// ===== 游戏引擎 =====
class GameEngine {
    constructor() {
        this.state = gameState;
        this.typingSpeed = 50;
        this.isTyping = false;
        this.init();
    }

    init() {
        this.loadGame();
        this.bindEvents();
        this.renderItems();
        this.renderAffection();
        this.loadStory();
        this.loadNovel();
    }

    // 绑定事件
    bindEvents() {
        // 模式切换
        document.getElementById('modeSwitch').addEventListener('click', () => {
            this.toggleMode();
        });

        // 存档按钮
        document.getElementById('saveBtn').addEventListener('click', () => {
            this.showSaveModal();
        });

        document.getElementById('loadBtn').addEventListener('click', () => {
            this.showLoadModal();
        });

        // 小说章节选择
        document.getElementById('novelChapterSelect').addEventListener('change', (e) => {
            this.loadNovelChapter(parseInt(e.target.value));
        });

        // 小说滚动进度
        document.querySelector('.novel-paper').addEventListener('scroll', (e) => {
            this.updateReadingProgress(e.target);
        });
    }

    // 切换模式
    toggleMode() {
        const gameMode = document.getElementById('gameMode');
        const novelMode = document.getElementById('novelMode');
        const modeSwitch = document.getElementById('modeSwitch');

        if (gameMode.style.display !== 'none') {
            gameMode.style.display = 'none';
            novelMode.style.display = 'flex';
            modeSwitch.querySelector('.mode-text').textContent = '游戏模式';
            modeSwitch.querySelector('.mode-icon').textContent = '🎮';
        } else {
            gameMode.style.display = 'flex';
            novelMode.style.display = 'none';
            modeSwitch.querySelector('.mode-text').textContent = '阅读模式';
            modeSwitch.querySelector('.mode-icon').textContent = '📖';
        }
    }

    // 条件路由判断
    getAutoNext(nodeId) {
        switch (nodeId) {
            case 'ending_check':
                if (this.state.flags.accepted_end) return 'ending1';
                if (this.state.flags.hidden_love) return 'ending2';
                if (this.state.flags.silent_end) return 'ending4';
                return 'ending3';
            case 'sequel_5':
                if (this.state.flags.terrace_talk) return 'sequel_5a';
                if (this.state.flags.shopping_route) return 'sequel_5b';
                return 'sequel_5b';
            case 'sequel_7':
                if (this.state.flags.second_confess_rejected) return 'sequel_7a';
                if (this.state.flags.let_go) return 'sequel_7b';
                if (this.state.flags.distant_goodbye) return 'sequel_7c';
                return 'sequel_7c';
            case 'final_ending':
                if (this.state.flags.still_hopeless) return 'final_ending1';
                if (this.state.flags.peaceful) return 'final_ending4';
                if (this.state.flags.forced_move_on || this.state.flags.blocked) return 'final_ending3';
                if (this.state.flags.let_go) return 'final_ending2';
                if (this.state.flags.distant_goodbye && !this.state.flags.blocked && !this.state.flags.peaceful) return 'final_ending3';
                return 'final_ending2';
            default:
                return null;
        }
    }

    // 加载剧情
    loadStory() {
        const node = STORY_DATA[this.state.currentNode];
        if (!node) return;

        this.updateChapterNav(node.chapter, node.chapterName);

        const storyContent = document.getElementById('storyContent');
        storyContent.innerHTML = '';

        // 打字机效果
        this.typeText(node.text, storyContent, () => {
            // 检查是否有条件自动路由
            const autoNext = this.getAutoNext(this.state.currentNode);
            if (autoNext) {
                const btnText = (this.state.currentNode === 'ending_check' || this.state.currentNode === 'final_ending')
                    ? '查看结局'
                    : '继续';
                this.renderAutoRouteButton(btnText, autoNext);
                return;
            }

            this.renderOptions(node.options);

            // 添加道具（节点级别）
            if (node.addItem) {
                this.addItem(node.addItem);
            }

            // 设置标记（节点级别）
            if (node.flag) {
                this.state.flags[node.flag] = true;
            }

            // 检查结局
            if (node.ending) {
                this.showEnding(node.endingData);
            }
        });
    }

    // 渲染自动路由按钮
    renderAutoRouteButton(text, nextNodeId) {
        const container = document.getElementById('optionsContainer');
        container.innerHTML = '';

        const btn = document.createElement('button');
        btn.className = 'option-btn';
        btn.textContent = text;
        btn.addEventListener('click', () => {
            this.state.currentNode = nextNodeId;
            this.saveGame();
            this.loadStory();
        });
        container.appendChild(btn);
    }

    // 打字机效果
    typeText(textArray, container, callback) {
        this.isTyping = true;
        let index = 0;

        const typeParagraph = () => {
            if (index >= textArray.length) {
                this.isTyping = false;
                if (callback) callback();
                return;
            }

            const p = document.createElement('p');
            p.className = 'story-text';
            container.appendChild(p);

            let charIndex = 0;
            const text = textArray[index];

            const typeChar = () => {
                if (charIndex < text.length) {
                    p.textContent += text[charIndex];
                    charIndex++;
                    setTimeout(typeChar, this.typingSpeed);
                } else {
                    index++;
                    setTimeout(typeParagraph, 200);
                }
            };

            typeChar();
        };

        typeParagraph();
    }

    // 渲染选项
    renderOptions(options) {
        const container = document.getElementById('optionsContainer');
        container.innerHTML = '';

        if (!options || options.length === 0) return;

        options.forEach((option, index) => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = option.text;
            btn.style.animationDelay = `${index * 0.1}s`;

            btn.addEventListener('click', () => {
                this.selectOption(option);
            });

            container.appendChild(btn);
        });
    }

    // 选择选项
    selectOption(option) {
        // 更新好感度
        if (option.affection) {
            this.state.affection += option.affection;
            this.renderAffection();
        }

        // 添加道具（选项级别）
        if (option.addItem) {
            this.addItem(option.addItem);
        }

        // 设置标记（选项级别）
        if (option.flag) {
            this.state.flags[option.flag] = true;
        }

        // 记录历史
        this.state.history.push(this.state.currentNode);

        // 进入下一节点
        if (option.next) {
            this.state.currentNode = option.next;
            this.saveGame();
            this.loadStory();
        }
    }

    // 更新章节导航
    updateChapterNav(chapter, chapterName) {
        this.state.chapter = chapter;
        document.getElementById('currentChapter').textContent = chapterName;

        const prevBtn = document.getElementById('prevChapter');
        const nextBtn = document.getElementById('nextChapter');

        prevBtn.disabled = chapter === 0;
        nextBtn.disabled = false;
    }

    // 渲染道具栏
    renderItems() {
        const container = document.getElementById('itemsContainer');
        container.innerHTML = '';

        const itemKeys = ['bear', 'milk_tea', 'umbrella', 'cake', 'beer'];

        itemKeys.forEach(key => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'item-icon';

            if (this.state.items.includes(key)) {
                const item = ITEMS[key];
                itemDiv.textContent = item.icon;
                itemDiv.innerHTML += `<span class="item-tooltip">${item.name}: ${item.desc}</span>`;
            } else {
                itemDiv.classList.add('empty');
                itemDiv.textContent = '·';
            }

            container.appendChild(itemDiv);
        });
    }

    // 添加道具
    addItem(itemKey) {
        if (!this.state.items.includes(itemKey)) {
            this.state.items.push(itemKey);
            this.renderItems();
            this.showToast(ITEMS[itemKey].icon, `获得道具: ${ITEMS[itemKey].name}`);
        }
    }

    // 渲染好感度
    renderAffection() {
        const heartsContainer = document.getElementById('affectionHearts');
        const valueDisplay = document.getElementById('affectionValue');

        heartsContainer.innerHTML = '';

        const maxHearts = 10;
        const activeHearts = Math.min(Math.floor(this.state.affection / 5), maxHearts);

        for (let i = 0; i < maxHearts; i++) {
            const heart = document.createElement('span');
            heart.className = 'heart';
            heart.textContent = '❤';

            if (i < activeHearts) {
                heart.classList.add('active');
            }

            heartsContainer.appendChild(heart);
        }

        valueDisplay.textContent = this.state.affection;
    }

    // 显示提示
    showToast(icon, text) {
        const toast = document.getElementById('itemToast');
        toast.querySelector('.toast-icon').textContent = icon;
        toast.querySelector('.toast-text').textContent = text;

        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // 显示结局
    showEnding(endingData) {
        const modal = document.getElementById('endingModal');
        document.getElementById('endingTitle').textContent = endingData.title;
        document.getElementById('endingDescription').textContent = endingData.description;

        const stats = document.getElementById('endingStats');
        stats.innerHTML = `
            <p>好感度: ${endingData.affection}</p>
            <p>收集道具: ${endingData.items.length > 0 ? endingData.items.map(i => ITEMS[i].name).join('、') : '无'}</p>
        `;

        modal.classList.add('active');

        // 记录结局
        if (!this.state.endings.includes(endingData.title)) {
            this.state.endings.push(endingData.title);
            this.saveGame();
        }
    }

    // 重新开始
    restartGame() {
        this.state = {
            currentNode: 'prologue_start',
            chapter: 0,
            affection: 0,
            items: [],
            endings: this.state.endings,
            flags: {},
            history: []
        };
        this.saveGame();
        this.closeModal('endingModal');
        this.renderItems();
        this.renderAffection();
        this.loadStory();
    }

    // 存档系统
    saveGame() {
        const saveData = {
            version: GAME_VERSION,
            state: this.state
        };
        localStorage.setItem('oujiang_game', JSON.stringify(saveData));
    }

    loadGame() {
        const saved = localStorage.getItem('oujiang_game');
        if (saved) {
            try {
                const saveData = JSON.parse(saved);
                if (saveData.version === GAME_VERSION && saveData.state) {
                    this.state = saveData.state;
                } else {
                    localStorage.removeItem('oujiang_game');
                    for (let i = 1; i <= 3; i++) {
                        localStorage.removeItem(`oujiang_save_${i}`);
                    }
                }
            } catch (e) {
                localStorage.removeItem('oujiang_game');
                for (let i = 1; i <= 3; i++) {
                    localStorage.removeItem(`oujiang_save_${i}`);
                }
            }
        }
    }

    showSaveModal() {
        const modal = document.getElementById('saveModal');
        const slotsContainer = document.getElementById('saveSlots');
        slotsContainer.innerHTML = '';

        for (let i = 1; i <= 3; i++) {
            const saved = localStorage.getItem(`oujiang_save_${i}`);
            const slot = document.createElement('div');
            slot.className = 'save-slot';

            if (saved) {
                try {
                    const saveData = JSON.parse(saved);
                    const state = saveData.state || saveData;
                    const isValid = saveData.version === GAME_VERSION || !saveData.version;
                    slot.innerHTML = `
                        <div class="slot-info">
                            <div class="slot-title">存档 ${i} - ${isValid ? (STORY_DATA[state.currentNode]?.chapterName || '未知') : '版本不兼容'}</div>
                            <div class="slot-time">好感度: ${state.affection || 0}</div>
                        </div>
                        <button class="slot-btn" onclick="game.saveToSlot(${i})">保存</button>
                    `;
                } catch (e) {
                    slot.innerHTML = `
                        <div class="slot-info">
                            <div class="slot-title">存档 ${i} - 已损坏</div>
                            <div class="slot-time">无法读取</div>
                        </div>
                        <button class="slot-btn" onclick="game.saveToSlot(${i})">保存</button>
                    `;
                }
            } else {
                slot.innerHTML = `
                    <div class="slot-info">
                        <div class="slot-title">存档 ${i} - 空</div>
                        <div class="slot-time">暂无存档</div>
                    </div>
                    <button class="slot-btn" onclick="game.saveToSlot(${i})">保存</button>
                `;
            }

            slotsContainer.appendChild(slot);
        }

        modal.classList.add('active');
    }

    showLoadModal() {
        const modal = document.getElementById('saveModal');
        const slotsContainer = document.getElementById('saveSlots');
        slotsContainer.innerHTML = '';

        for (let i = 1; i <= 3; i++) {
            const saved = localStorage.getItem(`oujiang_save_${i}`);
            const slot = document.createElement('div');
            slot.className = 'save-slot';

            if (saved) {
                try {
                    const saveData = JSON.parse(saved);
                    const state = saveData.state || saveData;
                    const isValid = saveData.version === GAME_VERSION || !saveData.version;
                    if (isValid) {
                        slot.innerHTML = `
                            <div class="slot-info">
                                <div class="slot-title">存档 ${i} - ${STORY_DATA[state.currentNode]?.chapterName || '未知'}</div>
                                <div class="slot-time">好感度: ${state.affection || 0}</div>
                            </div>
                            <button class="slot-btn" onclick="game.loadFromSlot(${i})">读取</button>
                        `;
                    } else {
                        slot.innerHTML = `
                            <div class="slot-info">
                                <div class="slot-title">存档 ${i} - 版本不兼容</div>
                                <div class="slot-time">需重新开始</div>
                            </div>
                        `;
                    }
                } catch (e) {
                    slot.innerHTML = `
                        <div class="slot-info">
                            <div class="slot-title">存档 ${i} - 已损坏</div>
                            <div class="slot-time">无法读取</div>
                        </div>
                    `;
                }
            } else {
                slot.innerHTML = `
                    <div class="slot-info">
                        <div class="slot-title">存档 ${i} - 空</div>
                        <div class="slot-time">暂无存档</div>
                    </div>
                `;
            }

            slotsContainer.appendChild(slot);
        }

        modal.classList.add('active');
    }

    saveToSlot(slot) {
        const saveData = {
            version: GAME_VERSION,
            state: this.state
        };
        localStorage.setItem(`oujiang_save_${slot}`, JSON.stringify(saveData));
        this.closeModal('saveModal');
        this.showToast('💾', '保存成功');
    }

    loadFromSlot(slot) {
        const saved = localStorage.getItem(`oujiang_save_${slot}`);
        if (saved) {
            try {
                const saveData = JSON.parse(saved);
                if (saveData.version === GAME_VERSION && saveData.state) {
                    this.state = saveData.state;
                    this.renderItems();
                    this.renderAffection();
                    this.loadStory();
                    this.closeModal('saveModal');
                    this.showToast('📂', '读取成功');
                } else {
                    this.showToast('⚠️', '存档版本不兼容，已重置');
                    localStorage.removeItem(`oujiang_save_${slot}`);
                }
            } catch (e) {
                localStorage.removeItem(`oujiang_save_${slot}`);
            }
        }
    }

    // 小说功能
    loadNovel() {
        this.loadNovelChapter(0);
    }

    loadNovelChapter(index) {
        const chapter = NOVEL_DATA[index];
        if (!chapter) return;
        document.getElementById('novelTitle').textContent = chapter.title;
        document.getElementById('novelContent').innerHTML = chapter.content
            .trim()
            .split('\n\n')
            .map(p => `<p>${p.trim()}</p>`)
            .join('');
    }

    updateReadingProgress(container) {
        const scrollTop = container.scrollTop;
        const scrollHeight = container.scrollHeight - container.clientHeight;
        const progress = Math.floor((scrollTop / scrollHeight) * 100) || 0;

        document.getElementById('readingProgress').style.width = `${progress}%`;
        document.getElementById('progressText').textContent = `${progress}%`;
    }

    // 关闭模态框
    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
}

// ===== 全局函数 =====
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function restartGame() {
    game.restartGame();
}

// ===== 初始化游戏 =====
let game;
document.addEventListener('DOMContentLoaded', () => {
    game = new GameEngine();
});
