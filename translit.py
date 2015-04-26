# Transliterate (e.g. ß -> ss)
def transLit(word):

    word = word.replace(u'\u00e4','ae')
    word = word.replace(u'\u00f6','oe')
    word = word.replace(u'\u00fc','ue')
    word = word.replace(u'\u00c4','ae')
    word = word.replace(u'\u00d6','oe')
    word = word.replace(u'\u00dc','ue')
    word = word.replace(u'\u00df','ss')

    return word
