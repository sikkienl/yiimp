FROM ubuntu:16.04 as baseimage1604
LABEL maintainer tpfuemp@github

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    automake autotools-dev build-essential dh-autoreconf \
    ca-certificates git \
    libboost-chrono-dev libboost-filesystem-dev libboost-program-options-dev libboost-system-dev libboost-test-dev libboost-thread-dev libevent-dev \
    libssl-dev libcurl4-openssl-dev libgmp-dev libsodium-dev libz-dev bsdmainutils curl libsqlite3-dev \
    libtool pkg-config && \
# 3. install db v4.8
    apt-get install -y --no-install-recommends software-properties-common && \
    add-apt-repository ppa:bitcoin/bitcoin && \
    apt-get update && \
    apt-get install -y --no-install-recommends libdb4.8-dev libdb4.8++-dev && \
    apt-get install -y --no-install-recommends mc psmisc wget protobuf-compiler libminiupnpc-dev g++ cmake bison libevent-dev libboost-dev libxml2-dev python3 flex && \
    apt-get clean

FROM baseimage1604 as coinwallet

# Clone the source code.
ARG STRATUM_UPDATEPASS
ARG CURRENT_GITHUB_COMMIT=0b196b80f17d0992d3f4d556f6fa787517c6a09c

RUN git clone https://github.com/ibithub/ibithub
WORKDIR /ibithub/src

# use the specific commit known to work
RUN git checkout $CURRENT_GITHUB_COMMIT

# apply patch if needed
#COPY docker.patch /tmp/
#RUN patch -p1 < /tmp/docker.patch

RUN make -f makefile.unix clean && make -f makefile.unix "USE_UPNP=-" && \
    strip ibithubd && cp ibithubd /usr/bin && \
    cd / && rm /ibithub -rf && \
    rm ~/.cache -rf && rm ~/.ccache -rf && rm ~/.cargo -rf

# blocknotify
COPY blocknotify.cpp /tmp/
WORKDIR /tmp
RUN gcc -DSTRATUM_UPDATEPASS=\"$STRATUM_UPDATEPASS\" -o blocknotify blocknotify.cpp && \
    mv blocknotify /usr/bin/ && rm -f /tmp/blocknotify.cpp

ARG walletuserid
ARG walletgroupid
RUN groupadd -g $walletgroupid walletuser && useradd -r -u $walletuserid -g walletuser walletuser
USER walletuser 
